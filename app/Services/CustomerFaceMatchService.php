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
        if ($this->identityProvider() === 'openbiometrics') {
            return $this->compareWithOpenBiometrics($idFront, $selfie);
        }

        $url = $this->serviceUrl();
        $timeout = max(5, min(90, (int) config('services.face_compare.timeout', 30)));

        if (!$url) {
            return $this->needsReview('missing_face_compare_service_url', 'Face comparison service is not configured. Admin must review the ID photo and selfie manually.');
        }

        try {
            $response = $this->httpClient($timeout)
                ->attach('id_front', fopen($idFront->getRealPath(), 'r'), $idFront->getClientOriginalName() ?: 'id-front.jpg')
                ->attach('selfie', fopen($selfie->getRealPath(), 'r'), $selfie->getClientOriginalName() ?: 'selfie.jpg')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('Customer face compare service unavailable', ['message' => $e->getMessage()]);

            return $this->needsReview('face_compare_unavailable', 'Face comparison service is unavailable. Admin must review the ID photo and selfie manually.');
        }

        $data = $response->json() ?: [];
        if (!$response->ok() || !($data['ok'] ?? false)) {
            Log::warning('Customer face compare service failed', [
                'status' => $response->status(),
                'error' => Str::limit($response->body(), 500, ''),
            ]);

            return $this->needsReview(
                (string) ($data['error'] ?? 'face_compare_failed'),
                (string) ($data['message'] ?? 'Face comparison could not verify the images. Admin must review them manually.'),
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
            default => 'Face comparison needs manual admin review.',
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


    private function compareWithOpenBiometrics(UploadedFile $idFront, UploadedFile $selfie): array
    {
        $baseUrl = $this->openBiometricsBaseUrl();
        $timeout = max(5, min(90, (int) config('services.openbiometrics.timeout', 30)));
        $threshold = (float) config('services.openbiometrics.face_threshold', 0.4);

        if (!$baseUrl) {
            return $this->needsReview('missing_openbiometrics_base_url', 'OpenBiometrics face verification is not configured yet.');
        }

        try {
            $response = $this->openBiometricsClient($timeout)
                ->attach('document', fopen($idFront->getRealPath(), 'r'), $idFront->getClientOriginalName() ?: 'id-front.jpg')
                ->attach('selfie', fopen($selfie->getRealPath(), 'r'), $selfie->getClientOriginalName() ?: 'selfie.jpg')
                ->post($baseUrl . '/api/v1/documents/verify', ['threshold' => $threshold]);
        } catch (\Throwable $e) {
            Log::warning('OpenBiometrics document face verification unavailable', ['message' => $e->getMessage()]);

            return $this->needsReview('openbiometrics_unavailable', 'OpenBiometrics face verification is unavailable. Admin must review manually.');
        }

        $data = $response->json() ?: [];
        if (!$response->ok()) {
            Log::warning('OpenBiometrics document face verification failed', [
                'status' => $response->status(),
                'error' => Str::limit($response->body(), 500, ''),
            ]);

            return $this->needsReview(
                (string) ($data['error'] ?? 'openbiometrics_face_verify_failed'),
                (string) ($data['message'] ?? 'OpenBiometrics could not verify the ID face and selfie. Admin must review manually.'),
                $data
            );
        }

        $score = is_numeric($data['similarity'] ?? null) ? (float) $data['similarity'] : null;
        $status = ($data['is_match'] ?? false) === true ? 'match' : 'mismatch';

        return [
            'ok' => $status === 'match',
            'status' => $status,
            'score' => $score,
            'threshold' => $threshold,
            'engine' => 'openbiometrics_document_verify',
            'message' => $this->messageForStatus($status),
            'error' => null,
            'details' => $data,
        ];
    }

    private function identityProvider(): string
    {
        $provider = strtolower((string) config('services.customer_identity.provider', 'current'));
        if ($provider === 'openbiometrics') {
            return $this->openBiometricsBaseUrl() ? 'openbiometrics' : 'current';
        }

        if ($provider !== 'staging' || !$this->openBiometricsBaseUrl()) {
            return 'current';
        }

        $userId = (string) (session('user.id') ?? data_get(session('user'), 'id', ''));
        $stagingIds = array_filter(array_map('trim', explode(',', (string) config('services.customer_identity.staging_user_ids', ''))));

        return $userId !== '' && in_array($userId, $stagingIds, true) ? 'openbiometrics' : 'current';
    }

    private function openBiometricsClient(int $timeout)
    {
        $token = trim((string) config('services.openbiometrics.api_key', ''));
        $client = Http::timeout($timeout)->acceptJson();

        return $token === '' ? $client : $client->withToken($token);
    }

    private function openBiometricsBaseUrl(): ?string
    {
        $url = rtrim(trim((string) config('services.openbiometrics.base_url', '')), '/');

        return $url === '' ? null : $url;
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
