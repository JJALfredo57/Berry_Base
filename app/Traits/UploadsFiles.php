<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

trait UploadsFiles
{
    // Image types that get resized/compressed
    private array $imageExts = ['jpg', 'jpeg', 'png', 'webp'];

    // Non-image types allowed (documents, etc.)
    private array $docExts = ['pdf', 'gif'];

    protected function uploadFile(UploadedFile $file, string $folder): string
    {
        $ext  = strtolower($file->getClientOriginalExtension());
        $diskName = config('filesystems.upload_disk', 'public');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        // Save as JPEG for all image types (smaller size), keep original ext for docs/gif
        $saveExt = in_array($ext, $this->imageExts) ? 'jpg' : $ext;
        $fn = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $saveExt;
        $path = $folder . '/' . $fn;

        if (in_array($ext, $this->imageExts)) {
            // Resize: max 1200px wide or tall, keep aspect ratio, 80% quality
            $encoded = Image::read($file->getRealPath())
                ->scaleDown(width: 1200, height: 1200)
                ->toJpeg(quality: 80);

            $disk->put($path, (string) $encoded, 'public');
        } else {
            $disk->putFileAs($folder, $file, $fn, 'public');
        }

        return $disk->url($path);
    }
}
