import os
import subprocess
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse

app = FastAPI(title="BerryBase OCR Service")

TOKEN = os.getenv("OCR_SERVICE_TOKEN", "").strip()
TESSERACT_BINARY = os.getenv("TESSERACT_BINARY", "tesseract")
DEFAULT_LANG = os.getenv("TESSERACT_LANG", "eng")
TIMEOUT = int(os.getenv("TESSERACT_TIMEOUT", "30"))
MAX_BYTES = int(os.getenv("MAX_UPLOAD_BYTES", str(8 * 1024 * 1024)))


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


@app.get("/health")
def health(authorization: str | None = Header(default=None)):
    check_auth(authorization)
    ok, output, error = tesseract_version()
    first_line = output.splitlines()[0] if output else None
    return {
        "ok": ok,
        "engine": "tesseract",
        "version": first_line,
        "language": DEFAULT_LANG,
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