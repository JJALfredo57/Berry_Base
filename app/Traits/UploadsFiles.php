<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait UploadsFiles
{
    protected function uploadFile(UploadedFile $file, string $folder): ?string
    {
        try {
            $ext      = strtolower($file->getClientOriginalExtension());
            $diskName = config('filesystems.upload_disk', 'public');
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk     = Storage::disk($diskName);

            $imagePayload = $this->compressedImagePayload($file);
            if ($imagePayload !== null) {
                $ext = 'jpg';
            }

            $fn   = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $path = $folder . '/' . $fn;

            if ($imagePayload !== null) {
                $disk->put($path, $imagePayload);
            } else {
                $disk->putFileAs($folder, $file, $fn);
            }

            return $disk->url($path);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('uploadFile failed: ' . $e->getMessage());
            return null;
        }
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
