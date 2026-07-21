<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait UploadsFiles
{
    protected function uploadFile(UploadedFile $file, string $folder): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $imagePayload = $this->compressedImagePayload($file);
        if ($imagePayload !== null) {
            $ext = 'jpg';
        }

        $fn = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $path = $folder . '/' . $fn;
        $primaryDisk = config('filesystems.upload_disk', 'public');
        $disks = array_values(array_unique(array_filter([$primaryDisk, 'public'])));

        foreach ($disks as $diskName) {
            try {
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk($diskName);

                if ($diskName === 'public') {
                    $disk->makeDirectory($folder);
                }

                $options = $diskName === 'public' ? ['visibility' => 'public'] : [];
                $stored = $imagePayload !== null
                    ? $disk->put($path, $imagePayload, $options)
                    : $disk->putFileAs($folder, $file, $fn, $options);

                if (!$stored) {
                    throw new \RuntimeException("Unable to store uploaded file on the [{$diskName}] disk.");
                }

                return $disk->url($path);
            } catch (\Throwable $e) {
                Log::warning('uploadFile disk failed', [
                    'disk' => $diskName,
                    'folder' => $folder,
                    'file' => $file->getClientOriginalName(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::error('uploadFile failed on all configured disks', [
            'disks' => $disks,
            'folder' => $folder,
            'file' => $file->getClientOriginalName(),
        ]);

        return null;
    }

    protected function deleteUploadedFile(?string $urlOrPath): bool
    {
        $path = $this->uploadedStoragePath($urlOrPath);
        if (!$path) {
            return false;
        }

        $primaryDisk = config('filesystems.upload_disk', 'public');
        $disks = array_values(array_unique(array_filter([$primaryDisk, 'public'])));
        $deleted = false;

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    $deleted = $disk->delete($path) || $deleted;
                }
            } catch (\Throwable $e) {
                Log::warning('deleteUploadedFile disk failed', [
                    'disk' => $diskName,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }

    protected function deleteUploadedFiles(array|string|null $paths): void
    {
        if (is_string($paths)) {
            $decoded = json_decode($paths, true);
            $paths = is_array($decoded) ? $decoded : [$paths];
        }

        foreach (($paths ?? []) as $path) {
            if (is_string($path)) {
                $this->deleteUploadedFile($path);
            }
        }
    }

    protected function deleteReplacedUploadedFile(?string $oldUrlOrPath, ?string $newUrlOrPath): bool
    {
        $oldPath = $this->uploadedStoragePath($oldUrlOrPath);
        $newPath = $this->uploadedStoragePath($newUrlOrPath);

        if (!$oldPath || $oldPath === $newPath) {
            return false;
        }

        return $this->deleteUploadedFile($oldUrlOrPath);
    }

    private function uploadedStoragePath(?string $urlOrPath): ?string
    {
        $value = trim((string) $urlOrPath);
        if ($value === '' || str_starts_with($value, 'data:')) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = ltrim($path, '/');

        $diskNames = array_values(array_unique(array_filter([config('filesystems.upload_disk', 'public'), 'public'])));
        foreach ($diskNames as $diskName) {
            $diskUrl = config("filesystems.disks.{$diskName}.url");
            if (!$diskUrl) {
                continue;
            }

            $diskBasePath = ltrim((string) parse_url($diskUrl, PHP_URL_PATH), '/');
            if ($diskBasePath !== '' && str_starts_with($path, rtrim($diskBasePath, '/') . '/')) {
                $path = substr($path, strlen(rtrim($diskBasePath, '/')) + 1);
                break;
            }
        }

        foreach ($diskNames as $diskName) {
            $bucket = config("filesystems.disks.{$diskName}.bucket");
            if (!$bucket) {
                continue;
            }

            $publicPrefix = 'storage/v1/object/public/' . trim((string) $bucket, '/') . '/';
            $signedPrefix = 'storage/v1/object/sign/' . trim((string) $bucket, '/') . '/';
            if (str_starts_with($path, $publicPrefix)) {
                $path = substr($path, strlen($publicPrefix));
                break;
            }
            if (str_starts_with($path, $signedPrefix)) {
                $path = substr($path, strlen($signedPrefix));
                break;
            }
            if (str_starts_with($path, trim((string) $bucket, '/') . '/uploads/')) {
                $path = substr($path, strlen(trim((string) $bucket, '/')) + 1);
                break;
            }
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (!str_starts_with($path, 'uploads/')) {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function compressedImagePayload(UploadedFile $file): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @file_get_contents($file->getRealPath());
        if ($source === false) {
            return null;
        }

        $img = @imagecreatefromstring($source);
        if (!$img) {
            return null;
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $maxPx = 1400;
        $scale = min($maxPx / max($width, 1), $maxPx / max($height, 1), 1);
        $outW = max(1, (int) round($width * $scale));
        $outH = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($outW, $outH);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $outW, $outH, $white);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $outW, $outH, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 78);
        $compressed = ob_get_clean();

        imagedestroy($img);
        imagedestroy($canvas);

        if (!$compressed || strlen($compressed) >= $file->getSize()) {
            return null;
        }

        return $compressed;
    }
}
