import os
import subprocess
import tempfile
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
    authorization: str | None = Header(default=None),
):
    check_auth(authorization)

    content = await file.read()
    if not content:
        return JSONResponse({"ok": False, "error": "empty_file", "message": "Uploaded file is empty."}, status_code=422)
    if len(content) > MAX_BYTES:
        return JSONResponse({"ok": False, "error": "file_too_large", "message": "Uploaded file is too large."}, status_code=413)

    suffix = Path(file.filename or "upload.jpg").suffix or ".jpg"
    with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
        tmp.write(content)
        tmp_path = tmp.name

    try:
        result = subprocess.run(
            [TESSERACT_BINARY, tmp_path, "stdout", "-l", lang or DEFAULT_LANG],
            capture_output=True,
            text=True,
            timeout=TIMEOUT,
            check=False,
        )
    except subprocess.TimeoutExpired:
        return JSONResponse({"ok": False, "error": "ocr_timeout", "message": "OCR timed out."}, status_code=504)
    except Exception as exc:
        return JSONResponse({"ok": False, "error": "ocr_unavailable", "message": str(exc)}, status_code=500)
    finally:
        try:
            os.remove(tmp_path)
        except OSError:
            pass

    text = (result.stdout or "").strip()
    if result.returncode != 0:
        return JSONResponse({
            "ok": False,
            "error": "ocr_failed",
            "message": "Tesseract could not read the uploaded image.",
            "details": (result.stderr or result.stdout or "")[:1000],
        }, status_code=422)

    if not text:
        return JSONResponse({"ok": False, "error": "empty_text", "message": "No readable text found."}, status_code=422)

    return {"ok": True, "engine": "tesseract", "text": text}


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
