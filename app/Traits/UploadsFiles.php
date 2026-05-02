<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

trait UploadsFiles
{
    private array $imageExts = ['jpg', 'jpeg', 'png', 'webp'];

    protected function uploadFile(UploadedFile $file, string $folder): string
    {
        $ext      = strtolower($file->getClientOriginalExtension());
        $diskName = config('filesystems.upload_disk', 'public');
        $disk     = Storage::disk($diskName);

        $saveExt = in_array($ext, $this->imageExts) ? 'jpg' : $ext;
        $fn   = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $saveExt;
        $path = $folder . '/' . $fn;

        if (in_array($ext, $this->imageExts)) {
            try {
                $encoded = Image::read($file->getRealPath())
                    ->scaleDown(width: 1200, height: 1200)
                    ->toJpeg(quality: 80);
                $disk->put($path, (string) $encoded);
            } catch (\Throwable $e) {
                // Fallback: store original file without resizing
                $disk->putFileAs($folder, $file, $fn);
            }
        } else {
            $disk->putFileAs($folder, $file, $fn);
        }

        return $disk->url($path);
    }
}
