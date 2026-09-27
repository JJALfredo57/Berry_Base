# BerryBase OCR Service

Small HTTP OCR service for BerryBase ID verification. Deploy this separately on a Docker-capable host such as Render, Railway, or Fly.io.

## Environment Variables

```env
OCR_SERVICE_TOKEN=change-this-secret
TESSERACT_LANG=eng
TESSERACT_TIMEOUT=30
MAX_UPLOAD_BYTES=8388608
```

## Endpoints

- `GET /health` checks if Tesseract can run.
- `POST /ocr` accepts multipart `file` and optional `lang`, then returns OCR text.

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
```

Then go to Super Admin > Platform Settings > ID Verification > Check OCR.