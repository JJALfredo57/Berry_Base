<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomerFaceMatchService
{
    public function compareUploadedFiles(UploadedFile $idFront, UploadedFile $selfie): array
    {
        $url = $this->serviceUrl();
        $timeout = max(5, min(90, (int) config('services.face_compare.timeout', 30)));

        if (!$url) {
            return $this->needsReview('missing_face_compare_service_url', 'Face comparison service is not configured. Face verification cannot continue right now.');
        }

        try {
            $response = $this->httpClient($timeout)
                ->attach('id_front', fopen($idFront->getRealPath(), 'r'), $idFront->getClientOriginalName() ?: 'id-front.jpg')
                ->attach('selfie', fopen($selfie->getRealPath(), 'r'), $selfie->getClientOriginalName() ?: 'selfie.jpg')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('Customer face compare service unavailable', ['message' => $e->getMessage()]);

            return $this->needsReview('face_compare_unavailable', 'Face comparison service is unavailable. Please try again when face verification is ready.');
        }

        $data = $response->json() ?: [];
        if (!$response->ok() || !($data['ok'] ?? false)) {
            Log::warning('Customer face compare service failed', [
                'status' => $response->status(),
                'error' => Str::limit($response->body(), 500, ''),
            ]);

            return $this->needsReview(
                (string) ($data['error'] ?? 'face_compare_failed'),
                (string) ($data['message'] ?? 'Face comparison could not verify the images. Please retake a clearer ID photo and selfie.'),
                $data
            );
        }

        $status = (string) ($data['status'] ?? 'needs_review');
        if (!in_array($status, ['match', 'mismatch', 'needs_review'], true)) {
            $status = 'needs_review';
        }

        return [
            'ok' => $status === 'match',
            'status' => $status,
            'score' => is_numeric($data['score'] ?? null) ? (float) $data['score'] : null,
            'threshold' => is_numeric($data['threshold'] ?? null) ? (float) $data['threshold'] : null,
            'engine' => (string) ($data['engine'] ?? 'external_face_compare'),
            'message' => (string) ($data['message'] ?? $this->messageForStatus($status)),
            'error' => $data['error'] ?? null,
            'details' => is_array($data['details'] ?? null) ? $data['details'] : [],
        ];
    }

    private function needsReview(string $error, string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'status' => 'needs_review',
            'score' => null,
            'threshold' => null,
            'engine' => 'external_face_compare',
            'message' => $message,
            'error' => $error,
            'details' => $details,
        ];
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            'match' => 'Selfie appears to match the face on the ID.',
            'mismatch' => 'Selfie does not appear to match the face on the ID. Please resubmit using your own valid ID.',
            default => 'Face must be clearly detected and matched before submission.',
        };
    }

    private function serviceUrl(): ?string
    {
        $explicit = trim((string) config('services.face_compare.service_url', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $ocrUrl = trim((string) config('services.ocr.service_url', ''));
        if ($ocrUrl === '') {
            return null;
        }

        return rtrim(preg_replace('~/ocr/?$~', '', $ocrUrl) ?: $ocrUrl, '/') . '/face-compare';
    }

    private function httpClient(int $timeout)
    {
        $token = trim((string) config('services.face_compare.service_token', ''));
        if ($token === '') {
            $token = trim((string) config('services.ocr.service_token', ''));
        }

        $client = Http::timeout($timeout)->acceptJson();

        return $token === '' ? $client : $client->withToken($token);
    }
}
