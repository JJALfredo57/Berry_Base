import os
import subprocess
import tempfile
import time
from pathlib import Path

import cv2
import numpy as np
from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse

app = FastAPI(title="BerryBase OCR and ID Verification Service")

TOKEN = os.getenv("OCR_SERVICE_TOKEN", "").strip()
TESSERACT_BINARY = os.getenv("TESSERACT_BINARY", "tesseract")
DEFAULT_LANG = os.getenv("TESSERACT_LANG", "eng")
TIMEOUT = int(os.getenv("TESSERACT_TIMEOUT", "30"))
MAX_BYTES = int(os.getenv("MAX_UPLOAD_BYTES", str(8 * 1024 * 1024)))
FACE_MATCH_THRESHOLD = float(os.getenv("FACE_MATCH_THRESHOLD", "0.60"))
FACE_MISMATCH_THRESHOLD = float(os.getenv("FACE_MISMATCH_THRESHOLD", "0.42"))

FACE_CASCADE = cv2.CascadeClassifier(str(Path(cv2.data.haarcascades) / "haarcascade_frontalface_default.xml"))


def check_auth(authorization: str | None) -> None:
    if not TOKEN:
        return
    expected = f"Bearer {TOKEN}"
    if authorization != expected:
        raise HTTPException(status_code=401, detail="Unauthorized")


def tesseract_version() -> tuple[bool, str, str | None]:
    try:
        result = subprocess.run(
            [TESSERACT_BINARY, "--version"],
            capture_output=True,
            text=True,
            timeout=min(TIMEOUT, 15),
            check=False,
        )
    except Exception as exc:
        return False, "", str(exc)

    output = (result.stdout or result.stderr or "").strip()
    if result.returncode != 0:
        return False, output, output or f"exit_code={result.returncode}"
    return True, output, None


def decode_image(content: bytes) -> np.ndarray | None:
    data = np.frombuffer(content, dtype=np.uint8)
    return cv2.imdecode(data, cv2.IMREAD_COLOR)


def normalize_ocr_gray(image: np.ndarray, target: float) -> np.ndarray:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image.copy()
    h, w = gray.shape[:2]
    longest = max(1, max(w, h))
    scale = min(2.5, target / longest)
    if scale < 0.98:
        gray = cv2.resize(gray, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    elif scale > 1.05:
        gray = cv2.resize(gray, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
    return gray


def crop_gray_region(gray: np.ndarray, x1: float, y1: float, x2: float, y2: float) -> np.ndarray:
    h, w = gray.shape[:2]
    left = max(0, min(w - 1, int(w * x1)))
    top = max(0, min(h - 1, int(h * y1)))
    right = max(left + 1, min(w, int(w * x2)))
    bottom = max(top + 1, min(h, int(h * y2)))
    return gray[top:bottom, left:right]


def prepare_id_type_variants(image: np.ndarray) -> list[tuple[str, np.ndarray]]:
    gray = normalize_ocr_gray(image, 1200.0)
    variants: list[tuple[str, np.ndarray]] = []

    regions = [
        ("header", crop_gray_region(gray, 0.04, 0.02, 0.96, 0.46)),
        ("body", crop_gray_region(gray, 0.22, 0.24, 0.98, 0.82)),
    ]

    for name, region in regions:
        if region.size == 0:
            continue
        enlarged = normalize_ocr_gray(region, 1450.0)
        clahe = cv2.createCLAHE(clipLimit=1.8, tileGridSize=(8, 8)).apply(enlarged)
        variants.append((f"{name}_gray", clahe))
        _, binary = cv2.threshold(clahe, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        variants.append((f"{name}_binary", binary))

    return variants or [("gray", normalize_ocr_gray(image, 900.0))]


def prepare_ocr_variants(image: np.ndarray, fast: bool = True, id_type: bool = False) -> list[tuple[str, np.ndarray]]:
    if id_type:
        return prepare_id_type_variants(image)

    variants: list[tuple[str, np.ndarray]] = []
    gray = normalize_ocr_gray(image, 1250.0 if fast else 1800.0)

    variants.append(("gray", gray))

    clahe = cv2.createCLAHE(clipLimit=2.0 if fast else 2.2, tileGridSize=(8, 8)).apply(gray)
    variants.append(("clahe", clahe))

    sharpen_kernel = np.array([[0, -1, 0], [-1, 5, -1], [0, -1, 0]])
    sharpened = cv2.filter2D(clahe, -1, sharpen_kernel)
    variants.append(("sharpened", sharpened))

    if fast:
        return variants

    denoised = cv2.fastNlMeansDenoising(gray, None, 12, 7, 21)
    denoised_clahe = cv2.createCLAHE(clipLimit=2.2, tileGridSize=(8, 8)).apply(denoised)
    _, otsu = cv2.threshold(denoised_clahe, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    variants.append(("otsu", otsu))

    adaptive = cv2.adaptiveThreshold(
        denoised_clahe,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        8,
    )
    variants.append(("adaptive", adaptive))

    return variants


def ocr_score(text: str) -> int:
    clean = " ".join(text.split())
    if not clean:
        return 0
    alpha_num = sum(1 for char in clean if char.isalnum())
    words = [word for word in clean.split() if any(char.isalnum() for char in word)]
    long_words = sum(1 for word in words if len(word) >= 3)
    return alpha_num + (len(words) * 4) + (long_words * 8)


def run_tesseract_file(path: str, lang: str, config: list[str], timeout: int) -> tuple[int, str, str]:
    result = subprocess.run(
        [TESSERACT_BINARY, path, "stdout", "-l", lang or DEFAULT_LANG, *config],
        capture_output=True,
        text=True,
        timeout=timeout,
        check=False,
    )
    return result.returncode, result.stdout or "", result.stderr or ""


def run_best_ocr(content: bytes, suffix: str, lang: str, mode: str = "fast") -> dict:
    image = decode_image(content)
    if image is None:
        return {"ok": False, "error": "invalid_image", "message": "Could not read the uploaded image.", "details": ""}

    mode = (mode or "fast").lower()
    id_type = mode == "id_type"
    fast = mode != "full"
    configs = [["--oem", "1", "--psm", "6"]]
    if not fast:
        configs.append(["--oem", "1", "--psm", "11"])

    budget = max(5, min(TIMEOUT, 7 if id_type else (8 if fast else 20)))
    per_pass_timeout = max(2, min(2 if id_type else (5 if fast else 8), budget))
    started = time.monotonic()
    best_text = ""
    best_details: dict = {"variant": None, "psm": None, "score": 0, "mode": "id_type" if id_type else ("fast" if fast else "full")}
    errors: list[str] = []

    for variant_name, variant in prepare_ocr_variants(image, fast=fast, id_type=id_type):
        if time.monotonic() - started >= budget:
            break
        with tempfile.NamedTemporaryFile(delete=False, suffix=".png") as tmp:
            tmp_path = tmp.name
        try:
            cv2.imwrite(tmp_path, variant)
            for config in configs:
                if time.monotonic() - started >= budget:
                    break
                try:
                    code, stdout, stderr = run_tesseract_file(tmp_path, lang, config, per_pass_timeout)
                except subprocess.TimeoutExpired:
                    errors.append(f"{variant_name}/psm{config[-1]} timed out")
                    continue

                text = (stdout or "").strip()
                score = ocr_score(text)
                if score > int(best_details["score"]):
                    best_text = text
                    best_details = {"variant": variant_name, "psm": config[-1], "score": score, "mode": "id_type" if id_type else ("fast" if fast else "full")}
                if score >= (35 if id_type else 90):
                    return {"ok": True, "text": best_text, "details": best_details}
                if code != 0 and stderr:
                    errors.append(stderr.strip()[:240])
        finally:
            try:
                os.remove(tmp_path)
            except OSError:
                pass

    if best_text:
        return {"ok": True, "text": best_text, "details": best_details}

    timeout_hit = any("timed out" in error for error in errors)
    return {
        "ok": False,
        "error": "ocr_timeout" if timeout_hit else "empty_text",
        "message": "ID scan took too long. Please retake the photo closer and steadier." if timeout_hit else "No readable text found. Move the ID closer, fill the guide, and avoid glare.",
        "details": " | ".join(errors[:3]),
    }

def largest_face(image: np.ndarray) -> tuple[np.ndarray | None, dict]:
    if FACE_CASCADE.empty():
        return None, {"error": "face_cascade_unavailable"}

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    min_side = max(48, int(min(gray.shape[:2]) * 0.06))
    faces = FACE_CASCADE.detectMultiScale(
        gray,
        scaleFactor=1.08,
        minNeighbors=4,
        minSize=(min_side, min_side),
    )

    if len(faces) == 0:
        return None, {"face_count": 0}

    x, y, w, h = max(faces, key=lambda box: box[2] * box[3])
    pad = int(max(w, h) * 0.22)
    x1 = max(0, x - pad)
    y1 = max(0, y - pad)
    x2 = min(image.shape[1], x + w + pad)
    y2 = min(image.shape[0], y + h + pad)
    crop = image[y1:y2, x1:x2]
    return crop, {"face_count": int(len(faces)), "box": [int(x), int(y), int(w), int(h)]}


def face_score(id_face: np.ndarray, selfie_face: np.ndarray) -> tuple[float, dict]:
    size = (160, 160)
    id_resized = cv2.resize(id_face, size)
    selfie_resized = cv2.resize(selfie_face, size)

    id_hsv = cv2.cvtColor(id_resized, cv2.COLOR_BGR2HSV)
    selfie_hsv = cv2.cvtColor(selfie_resized, cv2.COLOR_BGR2HSV)
    id_hist = cv2.calcHist([id_hsv], [0, 1], None, [32, 32], [0, 180, 0, 256])
    selfie_hist = cv2.calcHist([selfie_hsv], [0, 1], None, [32, 32], [0, 180, 0, 256])
    cv2.normalize(id_hist, id_hist, 0, 1, cv2.NORM_MINMAX)
    cv2.normalize(selfie_hist, selfie_hist, 0, 1, cv2.NORM_MINMAX)
    hist_score = max(0.0, min(1.0, float(cv2.compareHist(id_hist, selfie_hist, cv2.HISTCMP_CORREL))))

    id_gray = cv2.equalizeHist(cv2.cvtColor(id_resized, cv2.COLOR_BGR2GRAY))
    selfie_gray = cv2.equalizeHist(cv2.cvtColor(selfie_resized, cv2.COLOR_BGR2GRAY))
    orb = cv2.ORB_create(nfeatures=500)
    kp1, des1 = orb.detectAndCompute(id_gray, None)
    kp2, des2 = orb.detectAndCompute(selfie_gray, None)
    orb_score = 0.0
    good_matches = 0
    if des1 is not None and des2 is not None and len(des1) >= 8 and len(des2) >= 8:
        matcher = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)
        matches = sorted(matcher.match(des1, des2), key=lambda m: m.distance)
        good_matches = sum(1 for match in matches[:50] if match.distance <= 72)
        orb_score = min(1.0, good_matches / 22.0)

    id_edges = cv2.Canny(id_gray, 60, 140)
    selfie_edges = cv2.Canny(selfie_gray, 60, 140)
    edge_score = 1.0 - (np.mean(cv2.absdiff(id_edges, selfie_edges)) / 255.0)
    edge_score = max(0.0, min(1.0, float(edge_score)))

    score = (hist_score * 0.46) + (orb_score * 0.34) + (edge_score * 0.20)
    return max(0.0, min(1.0, float(score))), {
        "hist_score": round(hist_score, 4),
        "orb_score": round(orb_score, 4),
        "edge_score": round(edge_score, 4),
        "good_matches": int(good_matches),
    }


@app.get("/health")
def health(authorization: str | None = Header(default=None)):
    check_auth(authorization)
    ok, output, error = tesseract_version()
    first_line = output.splitlines()[0] if output else None
    face_ok = not FACE_CASCADE.empty()
    return {
        "ok": ok,
        "engine": "tesseract",
        "version": first_line,
        "language": DEFAULT_LANG,
        "face_compare": "available" if face_ok else "unavailable",
        "message": "OCR service is ready." if ok else "Tesseract is unavailable.",
        "error": error,
        "details": output[:1000],
    }


@app.post("/ocr")
async def ocr(
    file: UploadFile = File(...),
    lang: str = Form(default=DEFAULT_LANG),
    mode: str = Form(default="fast"),
    authorization: str | None = Header(default=None),
):
    check_auth(authorization)

    content = await file.read()
    if not content:
        return JSONResponse({"ok": False, "error": "empty_file", "message": "Uploaded file is empty."}, status_code=422)
    if len(content) > MAX_BYTES:
        return JSONResponse({"ok": False, "error": "file_too_large", "message": "Uploaded file is too large."}, status_code=413)

    suffix = Path(file.filename or "upload.jpg").suffix or ".jpg"
    try:
        ocr_result = run_best_ocr(content, suffix, lang or DEFAULT_LANG, mode or "fast")
    except subprocess.TimeoutExpired:
        return JSONResponse({"ok": False, "error": "ocr_timeout", "message": "OCR timed out."}, status_code=504)
    except Exception as exc:
        return JSONResponse({"ok": False, "error": "ocr_unavailable", "message": str(exc)}, status_code=500)

    if not ocr_result["ok"]:
        return JSONResponse({
            "ok": False,
            "error": ocr_result.get("error", "ocr_failed"),
            "message": ocr_result.get("message", "Tesseract could not read the uploaded image."),
            "details": str(ocr_result.get("details", ""))[:1000],
        }, status_code=422)

    return {"ok": True, "engine": "tesseract", "text": ocr_result["text"], "details": ocr_result.get("details", {})}


@app.post("/face-compare")
async def face_compare(
    id_front: UploadFile = File(...),
    selfie: UploadFile = File(...),
    authorization: str | None = Header(default=None),
):
    check_auth(authorization)

    id_content = await id_front.read()
    selfie_content = await selfie.read()
    if not id_content or not selfie_content:
        return JSONResponse({"ok": False, "error": "empty_file", "message": "ID and selfie images are required."}, status_code=422)
    if len(id_content) > MAX_BYTES or len(selfie_content) > MAX_BYTES:
        return JSONResponse({"ok": False, "error": "file_too_large", "message": "One of the uploaded images is too large."}, status_code=413)

    id_image = decode_image(id_content)
    selfie_image = decode_image(selfie_content)
    if id_image is None or selfie_image is None:
        return JSONResponse({"ok": False, "error": "invalid_image", "message": "Could not read the ID or selfie image."}, status_code=422)

    id_face, id_details = largest_face(id_image)
    selfie_face, selfie_details = largest_face(selfie_image)
    if id_face is None or selfie_face is None:
        missing = "ID photo" if id_face is None else "selfie"
        return {
            "ok": True,
            "status": "needs_review",
            "score": None,
            "threshold": FACE_MATCH_THRESHOLD,
            "engine": "opencv_face_compare",
            "message": f"Could not detect a clear face in the {missing}. Admin must review manually.",
            "details": {"id": id_details, "selfie": selfie_details},
        }

    score, score_details = face_score(id_face, selfie_face)
    if score >= FACE_MATCH_THRESHOLD:
        status = "match"
        message = "Selfie appears to match the face on the ID."
    elif score < FACE_MISMATCH_THRESHOLD:
        status = "mismatch"
        message = "Selfie does not appear to match the face on the ID."
    else:
        status = "needs_review"
        message = "Face comparison is inconclusive. Admin must review manually."

    return {
        "ok": True,
        "status": status,
        "score": round(score, 4),
        "threshold": FACE_MATCH_THRESHOLD,
        "engine": "opencv_face_compare",
        "message": message,
        "details": {"id": id_details, "selfie": selfie_details, "scores": score_details},
    }
