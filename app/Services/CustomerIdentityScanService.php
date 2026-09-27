<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class CustomerIdentityScanService
{
    public function __construct(private IdentityVerificationSettingsService $settings)
    {
    }

    public function healthCheck(): array
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
            'binary' => $binary,
            'language' => $language,
            'timeout' => $timeout,
            'version' => $firstLine ?: 'Tesseract available',
            'error' => null,
            'message' => 'OCR engine is available and can run on this server.',
            'details' => Str::limit($output, 500, ''),
        ];
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
                    'engine' => 'tesseract',
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
                    'engine' => 'tesseract',
                    'error' => 'pdf_not_supported',
                ],
            ]);
        }

        $ocr = $this->runTesseract($path);
        if ($deleteAfter && is_file($path)) {
            @unlink($path);
        }

        if (!$ocr['ok']) {
            return array_replace_recursive($base, [
                'scan_status' => $ocr['status'],
                'id_type_match_status' => 'needs_review',
                'id_type_match_warning' => $ocr['message'],
                'scan_result' => [
                    'engine' => 'tesseract',
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
                'engine' => 'tesseract',
                'text_preview' => Str::limit(preg_replace('/\s+/', ' ', trim($ocr['text'])), 1200, ''),
                'text_length' => mb_strlen($ocr['text']),
                'scores' => $match['scores'],
                'matched_keywords' => $match['matched_keywords'],
            ],
        ]);
    }
    public function evaluateText(string $selectedIdType, string $text): array
    {
        $scores = [];
        $matchedKeywords = [];
        $normalizedText = $this->normalizeForMatch($text);

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

                if (str_contains($normalizedText, $normalizedPhrase)) {
                    $scores[$name] = ($scores[$name] ?? 0) + max(1, substr_count($normalizedPhrase, ' ') + 1);
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
                'status' => 'needs_review',
                'error' => 'ocr_failed',
                'message' => 'OCR could not read this ID clearly. Please review it manually.',
            ];
        }

        $text = trim($result->output());
        if ($text === '') {
            return [
                'ok' => false,
                'status' => 'needs_review',
                'error' => 'empty_text',
                'message' => 'OCR ran but did not find readable text. Please review the ID manually.',
            ];
        }

        return ['ok' => true, 'text' => $text];
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

    private function cleanupTempFile(array $file): void
    {
        if (!empty($file['temp']) && !empty($file['path']) && is_file($file['path'])) {
            @unlink($file['path']);
        }
    }

    private function normalizeForMatch(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
