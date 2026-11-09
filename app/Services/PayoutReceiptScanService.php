<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Http;

class PayoutReceiptScanService
{
    public function scan(UploadedFile $file, object $payout): array
    {
        $path = $file->getRealPath();
        $text = $this->readText($path);
        $compact = preg_replace('/\s+/', ' ', strtoupper($text));
        $reference = $this->reference($compact);
        $amounts = $this->amounts($compact);
        $expected = number_format((float) $payout->net_amount, 2, '.', '');
        $amountMatch = in_array($expected, $amounts, true);
        $account = preg_replace('/\D/', '', (string) ($payout->payout_account_number ?? ''));
        $accountMatch = $account !== '' && (str_contains(preg_replace('/\D/', '', $compact), $account) || str_contains($compact, strtoupper((string) ($payout->payout_account_name ?? ''))));
        $receiptHint = str_contains($compact, 'GCASH') || str_contains($compact, 'REFERENCE') || str_contains($compact, 'SUCCESSFUL');
        $errors = [];
        if (mb_strlen(trim($text)) < 12) $errors[] = 'The receipt image is not readable. Upload a clearer, uncropped screenshot.';
        if (!$receiptHint) $errors[] = 'This image does not look like a supported payment receipt.';
        if (!$reference) $errors[] = 'No transaction reference number was found in the receipt.';
        if (!$amountMatch) $errors[] = 'The receipt amount does not match this payout.';
        if (!$accountMatch) $errors[] = 'The receipt recipient does not match the seller’s verified payout details.';
        return ['ok' => !$errors, 'reference_number' => $reference, 'errors' => $errors, 'text_length' => mb_strlen($text), 'amount_match' => $amountMatch, 'account_match' => $accountMatch];
    }
    private function readText(string $path): string
    {
        $url = trim((string) config('services.ocr.service_url', ''));
        if (strtolower((string) config('services.ocr.driver', 'auto')) !== 'local' && $url !== '') {
            try {
                $client = Http::timeout(max(5, min(45, (int) config('services.ocr.service_timeout', 30))))->acceptJson();
                if ($token = trim((string) config('services.ocr.service_token', ''))) $client = $client->withToken($token);
                $response = $client->attach('file', fopen($path, 'r'), basename($path))->post($url, ['lang' => config('services.ocr.tesseract_lang', 'eng'), 'mode' => 'payout_receipt']);
                if ($response->successful()) return (string) ($response->json('text') ?? $response->json('data.text') ?? '');
            } catch (\Throwable) { /* fall through to the local OCR engine */ }
        }
        $binary = (string) config('services.ocr.tesseract_binary', 'tesseract');
        $result = Process::timeout(max(5, min(60, (int) config('services.ocr.tesseract_timeout', 20))))->run([$binary, $path, 'stdout', '-l', (string) config('services.ocr.tesseract_lang', 'eng')]);
        return $result->successful() ? $result->output() : '';
    }
    private function reference(string $text): ?string
    {
        if (preg_match('/(?:REFERENCE|REF(?:ERENCE)?\s*(?:NO|NUMBER)?|TRANSACTION\s*(?:ID|NO)?)[\s:#-]*([A-Z0-9-]{6,})/', $text, $m)) return $m[1];
        return null;
    }
    private function amounts(string $text): array
    {
        preg_match_all('/(?:PHP|P|₱)\s*([0-9,]+(?:\.\d{2})?)/u', $text, $m);
        return array_values(array_unique(array_map(fn ($value) => number_format((float) str_replace(',', '', $value), 2, '.', ''), $m[1] ?? [])));
    }
}
