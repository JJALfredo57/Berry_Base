<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AssetDownloadController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'name' => ['nullable', 'string', 'max:160'],
        ]);

        $url = $validated['url'];
        if (!$this->isAllowedUrl($url)) {
            abort(403);
        }

        try {
            $response = Http::timeout(25)->get($url);
        } catch (\Throwable) {
            abort(404);
        }
        if (!$response->successful()) {
            abort(404);
        }

        $contentType = strtolower((string) $response->header('Content-Type', 'image/jpeg'));
        if (!str_starts_with($contentType, 'image/')) {
            abort(415);
        }

        $filename = $this->filename($validated['name'] ?? null, $url, $contentType);

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if ($appHost && $host === $appHost) {
            return str_starts_with($path, '/storage/') || str_starts_with($path, '/images/');
        }

        return str_ends_with($host, '.supabase.co')
            && str_starts_with($path, '/storage/v1/object/public/');
    }

    private function filename(?string $requested, string $url, string $contentType): string
    {
        $name = trim((string) $requested);
        if ($name === '') {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $name = basename($path) ?: 'berry-base-image';
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'berry-base-image';
        if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $name)) {
            $name .= match (true) {
                str_contains($contentType, 'png') => '.png',
                str_contains($contentType, 'webp') => '.webp',
                str_contains($contentType, 'gif') => '.gif',
                default => '.jpg',
            };
        }

        return $name;
    }
}
