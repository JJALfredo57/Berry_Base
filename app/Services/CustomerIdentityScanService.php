<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class CustomerIdentityScanService
{
    public function __construct(private IdentityVerificationSettingsService $settings)
    {
    }

    public function healthCheck(): array
    {
        return $this->ocrDriver() === 'http'
            ? $this->healthCheckHttp()
            : $this->healthCheckTesseract();
    }

    public function scan(string $selectedIdType, ?string $frontPath): array
    {
        $base = $this->baseResult($selectedIdType);
        $file = $this->resolveFile($frontPath);

        if (!$file['path']) {
            return array_replace_recursive($base, [
                'scan_status' => 'needs_review',
                'id_type_match_status' => 'needs_review',
                'id_type_match_warning' => 'OCR could not access the uploaded ID file. Please review it manually.',
                'scan_result' => [
                    'engine' => $this->ocrDriver(),
                    'error' => $file['error'] ?? 'file_not_found',
                ],
            ]);
        }

        return $this->scanLocalFile($selectedIdType, $file['path'], !empty($file['temp']));
    }

    public function scanUploadedFile(string $selectedIdType, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            $extension = 'jpg';
        }

        $tmp = tempnam(storage_path('app'), 'ocr_upload_');
        $target = $tmp . '.' . $extension;
        rename($tmp, $target);
        copy($file->getRealPath(), $target);

        try {
            return $this->scanLocalFile($selectedIdType, $target, true);
        } catch (\Throwable $e) {
            if (is_file($target)) {
                @unlink($target);
            }
            throw $e;
        }
    }

    public function evaluateText(string $selectedIdType, string $text): array
    {
        $scores = [];
        $matchedKeywords = [];
        $normalizedText = $this->normalizeForMatch($text);
        $compactText = str_replace(' ', '', $normalizedText);

        foreach ($this->settings->types() as $type) {
            $name = (string) ($type['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $phrases = array_values(array_unique(array_filter(array_merge(
                [$name],
                is_array($type['keywords'] ?? null) ? $type['keywords'] : []
            ))));

            foreach ($phrases as $phrase) {
                $normalizedPhrase = $this->normalizeForMatch((string) $phrase);
                if ($normalizedPhrase === '' || mb_strlen($normalizedPhrase) < 3) {
                    continue;
                }

                $compactPhrase = str_replace(' ', '', $normalizedPhrase);
                $phraseTokens = array_values(array_filter(explode(' ', $normalizedPhrase), fn ($token) => mb_strlen($token) >= 3));
                $hasExact = str_contains($normalizedText, $normalizedPhrase);
                $hasCompact = $compactPhrase !== '' && mb_strlen($compactPhrase) >= 3 && str_contains($compactText, $compactPhrase);
                $hasAllTokens = count($phraseTokens) >= 2 && collect($phraseTokens)->every(fn ($token) => str_contains($normalizedText, $token));

                if ($hasExact || $hasCompact || $hasAllTokens) {
                    $scores[$name] = ($scores[$name] ?? 0) + max(1, substr_count($normalizedPhrase, ' ') + 1) + ($hasExact ? 2 : 0) + ($hasCompact ? 1 : 0);
                    $matchedKeywords[$name][] = $phrase;
                }
            }
        }

        arsort($scores);
        $detected = array_key_first($scores);

        if (!$detected) {
            return [
                'status' => 'needs_review',
                'detected_id_type' => null,
                'warning' => 'OCR ran but could not confidently detect the ID type. Please review the uploaded ID manually.',
                'scores' => $scores,
                'matched_keywords' => $matchedKeywords,
            ];
        }

        if ($detected !== $selectedIdType) {
            return [
                'status' => 'mismatch',
                'detected_id_type' => $detected,
                'warning' => "Selected ID type is {$selectedIdType}, but OCR detected {$detected}. Please ask the customer to resubmit the correct ID.",
                'scores' => $scores,
                'matched_keywords' => $matchedKeywords,
            ];
        }

        return [
            'status' => 'match',
            'detected_id_type' => $detected,
            'warning' => null,
            'scores' => $scores,
            'matched_keywords' => $matchedKeywords,
        ];
    }

    private function scanLocalFile(string $selectedIdType, string $path, bool $deleteAfter = false): array
    {
        $base = $this->baseResult($selectedIdType);

        if (in_array(strtolower((string) pathinfo($path, PATHINFO_EXTENSION)), ['pdf'], true)) {
            if ($deleteAfter && is_file($path)) {
                @unlink($path);
            }

            return array_replace_recursive($base, [
                'scan_status' => 'unsupported_file',
                'id_type_match_status' => 'needs_review',
                'id_type_match_warning' => 'OCR currently supports image uploads only. Please scan or upload a JPG, PNG, or WebP image.',
                'scan_result' => [
                    'engine' => $this->ocrDriver(),
                    'error' => 'pdf_not_supported',
                ],
            ]);
        }

        $ocr = $this->runOcr($path);
        if ($deleteAfter && is_file($path)) {
            @unlink($path);
        }

        if (!$ocr['ok']) {
            return array_replace_recursive($base, [
                'scan_status' => $ocr['status'],
                'id_type_match_status' => 'needs_review',
                'id_type_match_warning' => $ocr['message'],
                'scan_result' => [
                    'engine' => $ocr['engine'] ?? $this->ocrDriver(),
                    'error' => $ocr['error'],
                    'message' => $ocr['message'],
                ],
            ]);
        }

        $match = $this->evaluateText($selectedIdType, $ocr['text']);

        return array_replace_recursive($base, [
            'scan_status' => 'scanned',
            'id_type_match_status' => $match['status'],
            'id_type_scan_detected' => $match['detected_id_type'],
            'id_type_match_warning' => $match['warning'],
            'scan_result' => [
                'engine' => $ocr['engine'] ?? $this->ocrDriver(),
                'text_preview' => Str::limit(preg_replace('/\s+/', ' ', trim($ocr['text'])), 1200, ''),
                'text_length' => mb_strlen($ocr['text']),
                'scores' => $match['scores'],
                'matched_keywords' => $match['matched_keywords'],
            ],
        ]);
    }

    private function baseResult(string $selectedIdType): array
    {
        return [
            'scan_status' => 'needs_review',
            'id_type_match_status' => 'needs_review',
            'id_type_scan_expected' => $selectedIdType,
            'id_type_scan_detected' => null,
            'id_type_match_warning' => null,
            'scan_result' => [],
        ];
    }

    private function runOcr(string $path): array
    {
        return $this->ocrDriver() === 'http'
            ? $this->runHttpOcr($path)
            : $this->runTesseract($path);
    }

    private function ocrDriver(): string
    {
        $driver = strtolower((string) config('services.ocr.driver', 'auto'));
        if ($driver === 'auto') {
            return filled(config('services.ocr.service_url')) ? 'http' : 'local';
        }

        return in_array($driver, ['http', 'local'], true) ? $driver : 'local';
    }

    private function healthCheckHttp(): array
    {
        $url = $this->ocrHealthUrl();
        $timeout = max(3, min(30, (int) config('services.ocr.service_timeout', 20)));

        if (!$url) {
            return [
                'ok' => false,
                'driver' => 'http',
                'binary' => 'external OCR service',
                'language' => (string) config('services.ocr.tesseract_lang', 'eng'),
                'timeout' => $timeout,
                'version' => null,
                'error' => 'missing_ocr_service_url',
                'message' => 'OCR HTTP service URL is not configured.',
                'details' => 'Set OCR_SERVICE_URL in Laravel Cloud.',
            ];
        }

        try {
            $response = $this->ocrHttpClient($timeout)->get($url);
        } catch (\Throwable $e) {
            Log::warning('Customer ID OCR HTTP health check unavailable', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'driver' => 'http',
                'binary' => 'external OCR service',
                'language' => (string) config('services.ocr.tesseract_lang', 'eng'),
                'timeout' => $timeout,
                'version' => null,
                'error' => 'ocr_service_unavailable',
                'message' => 'OCR service is unreachable from this server.',
                'details' => Str::limit($e->getMessage(), 500, ''),
            ];
        }

        $data = $response->json() ?: [];
        $ok = $response->ok() && (bool) ($data['ok'] ?? false);

        return [
            'ok' => $ok,
            'driver' => 'http',
            'binary' => 'external OCR service',
            'language' => (string) ($data['language'] ?? config('services.ocr.tesseract_lang', 'eng')),
            'timeout' => $timeout,
            'version' => (string) ($data['version'] ?? 'Not detected'),
            'error' => $ok ? null : (string) ($data['error'] ?? 'ocr_service_failed'),
            'message' => $ok ? 'OCR HTTP service is available.' : (string) ($data['message'] ?? 'OCR HTTP service failed the health check.'),
            'details' => Str::limit((string) ($data['details'] ?? $response->body()), 500, ''),
            'status_code' => $response->status(),
        ];
    }

    private function healthCheckTesseract(): array
    {
        $binary = (string) config('services.ocr.tesseract_binary', 'tesseract');
        $language = (string) config('services.ocr.tesseract_lang', 'eng');
        $timeout = max(3, min(15, (int) config('services.ocr.tesseract_timeout', 20)));

        try {
            $result = Process::timeout($timeout)->run([$binary, '--version']);
        } catch (\Throwable $e) {
            Log::warning('Customer ID OCR health check unavailable', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'driver' => 'local',
                'binary' => $binary,
                'language' => $language,
                'timeout' => $timeout,
                'version' => null,
                'error' => 'ocr_unavailable',
                'message' => 'OCR engine is unavailable. The server cannot run the configured Tesseract binary.',
                'details' => Str::limit($e->getMessage(), 500, ''),
            ];
        }

        $output = trim($result->output() ?: $result->errorOutput());
        $firstLine = trim((string) strtok($output, "\r\n"));

        if (!$result->successful()) {
            Log::warning('Customer ID OCR health check failed', [
                'exit_code' => $result->exitCode(),
                'error' => Str::limit($result->errorOutput(), 500, ''),
            ]);

            return [
                'ok' => false,
                'driver' => 'local',
                'binary' => $binary,
                'language' => $language,
                'timeout' => $timeout,
                'version' => $firstLine ?: null,
                'error' => 'ocr_failed',
                'message' => 'OCR engine responded but failed the health check.',
                'details' => Str::limit($result->errorOutput() ?: $result->output(), 500, ''),
                'exit_code' => $result->exitCode(),
            ];
        }

        return [
            'ok' => true,
            'driver' => 'local',
            'binary' => $binary,
            'language' => $language,
            'timeout' => $timeout,
            'version' => $firstLine ?: 'Tesseract available',
            'error' => null,
            'message' => 'OCR engine is available and can run on this server.',
            'details' => Str::limit($output, 500, ''),
        ];
    }

    private function runHttpOcr(string $path): array
    {
        $url = trim((string) config('services.ocr.service_url', ''));
        $language = (string) config('services.ocr.tesseract_lang', 'eng');
        $timeout = max(5, min(60, (int) config('services.ocr.service_timeout', 20)));

        if ($url === '') {
            return [
                'ok' => false,
                'engine' => 'http',
                'status' => 'ocr_unavailable',
                'error' => 'missing_ocr_service_url',
                'message' => 'OCR HTTP service URL is not configured. Please review the uploaded ID manually.',
            ];
        }

        try {
            $response = $this->ocrHttpClient($timeout)
                ->attach('file', fopen($path, 'r'), basename($path))
                ->post($url, ['lang' => $language]);
        } catch (\Throwable $e) {
            Log::warning('Customer ID OCR HTTP service unavailable', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'engine' => 'http',
                'status' => 'ocr_unavailable',
                'error' => 'ocr_service_unavailable',
                'message' => 'OCR service is not available. Please review the uploaded ID manually.',
            ];
        }

        $data = $response->json() ?: [];
        if (!$response->ok() || !($data['ok'] ?? false)) {
            Log::warning('Customer ID OCR HTTP service failed', [
                'status' => $response->status(),
                'error' => Str::limit($response->body(), 500, ''),
            ]);

            return [
                'ok' => false,
                'engine' => 'http',
                'status' => 'needs_review',
                'error' => (string) ($data['error'] ?? 'ocr_service_failed'),
                'message' => (string) ($data['message'] ?? 'OCR service could not read this ID clearly. Please review it manually.'),
            ];
        }

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return [
                'ok' => false,
                'engine' => 'http',
                'status' => 'needs_review',
                'error' => 'empty_text',
                'message' => 'OCR service ran but did not find readable text. Please review the ID manually.',
            ];
        }

        return ['ok' => true, 'engine' => 'http', 'text' => $text];
    }

    private function runTesseract(string $path): array
    {
        $binary = (string) config('services.ocr.tesseract_binary', 'tesseract');
        $language = (string) config('services.ocr.tesseract_lang', 'eng');
        $timeout = max(5, min(60, (int) config('services.ocr.tesseract_timeout', 20)));

        try {
            $result = Process::timeout($timeout)->run([$binary, $path, 'stdout', '-l', $language]);
        } catch (\Throwable $e) {
            Log::warning('Customer ID OCR unavailable', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'engine' => 'tesseract',
                'status' => 'ocr_unavailable',
                'error' => 'ocr_unavailable',
                'message' => 'OCR engine is not available. Please review the uploaded ID manually.',
            ];
        }

        if (!$result->successful()) {
            Log::warning('Customer ID OCR failed', [
                'exit_code' => $result->exitCode(),
                'error' => Str::limit($result->errorOutput(), 500, ''),
            ]);

            return [
                'ok' => false,
                'engine' => 'tesseract',
                'status' => 'needs_review',
                'error' => 'ocr_failed',
                'message' => 'OCR could not read this ID clearly. Please review it manually.',
            ];
        }

        $text = trim($result->output());
        if ($text === '') {
            return [
                'ok' => false,
                'engine' => 'tesseract',
                'status' => 'needs_review',
                'error' => 'empty_text',
                'message' => 'OCR ran but did not find readable text. Please review the ID manually.',
            ];
        }

        return ['ok' => true, 'engine' => 'tesseract', 'text' => $text];
    }

    private function ocrHttpClient(int $timeout)
    {
        $token = trim((string) config('services.ocr.service_token', ''));
        $client = Http::timeout($timeout)->acceptJson();

        return $token === '' ? $client : $client->withToken($token);
    }

    private function ocrHealthUrl(): ?string
    {
        $explicit = trim((string) config('services.ocr.service_health_url', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $url = trim((string) config('services.ocr.service_url', ''));
        if ($url === '') {
            return null;
        }

        return rtrim(preg_replace('~/ocr/?$~', '', $url) ?: $url, '/') . '/health';
    }

    private function resolveFile(?string $urlOrPath): array
    {
        $value = trim((string) $urlOrPath);
        if ($value === '') {
            return ['path' => null, 'error' => 'empty_path'];
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $candidates = [];
        if (str_starts_with($path, 'uploads/')) {
            $candidates[] = storage_path('app/public/' . $path);
            $candidates[] = public_path('storage/' . $path);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return ['path' => $candidate, 'temp' => false];
            }
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->downloadRemoteFile($value);
        }

        return ['path' => null, 'error' => 'file_not_found'];
    }

    private function downloadRemoteFile(string $url): array
    {
        try {
            $response = Http::timeout(12)->get($url);
            if (!$response->ok()) {
                return ['path' => null, 'error' => 'remote_download_failed'];
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > 8 * 1024 * 1024) {
                return ['path' => null, 'error' => 'remote_file_too_large_or_empty'];
            }

            $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
                $extension = 'jpg';
            }

            $tmp = tempnam(storage_path('app'), 'ocr_id_');
            $target = $tmp . '.' . $extension;
            rename($tmp, $target);
            file_put_contents($target, $body);

            return ['path' => $target, 'temp' => true];
        } catch (\Throwable $e) {
            Log::warning('Customer ID OCR remote download failed', ['message' => $e->getMessage()]);

            return ['path' => null, 'error' => 'remote_download_exception'];
        }
    }

    private function normalizeForMatch(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}