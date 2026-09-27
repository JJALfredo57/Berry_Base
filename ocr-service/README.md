# BerryBase OCR and Face Compare Service

Small HTTP service for BerryBase ID verification. It provides OCR for ID type checks and lightweight face comparison between the ID photo and selfie. Deploy this separately on a Docker-capable host such as Render, Railway, or Fly.io.

## Environment Variables

```env
OCR_SERVICE_TOKEN=change-this-secret
TESSERACT_LANG=eng
TESSERACT_TIMEOUT=30
MAX_UPLOAD_BYTES=8388608
FACE_MATCH_THRESHOLD=0.60
FACE_MISMATCH_THRESHOLD=0.42
```

## Endpoints

- `GET /health` checks if Tesseract can run and reports face compare availability.
- `POST /ocr` accepts multipart `file` and optional `lang`, then returns OCR text.
- `POST /face-compare` accepts multipart `id_front` and `selfie`, then returns `match`, `mismatch`, or `needs_review` with a confidence score.

If `OCR_SERVICE_TOKEN` is set, call both endpoints with:

```txt
Authorization: Bearer change-this-secret
```

## Laravel Cloud Variables

After deploying this service, set these in Laravel Cloud:

```env
OCR_DRIVER=http
OCR_SERVICE_URL=https://your-ocr-service.example/ocr
OCR_SERVICE_HEALTH_URL=https://your-ocr-service.example/health
OCR_SERVICE_TOKEN=change-this-secret
OCR_TESSERACT_LANG=eng
OCR_SERVICE_TIMEOUT=30
FACE_COMPARE_SERVICE_URL=https://your-ocr-service.example/face-compare
FACE_COMPARE_SERVICE_TOKEN=change-this-secret
FACE_COMPARE_SERVICE_TIMEOUT=30
```

Then go to Super Admin > Platform Settings > ID Verification > Check OCR.
## Face Compare Notes

The face comparison endpoint uses OpenCV face detection and image similarity scoring. It is useful as an automatic risk signal, but borderline results should still be reviewed by an admin. Clear mismatches are blocked by Laravel.
