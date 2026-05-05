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

            $fn   = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $path = $folder . '/' . $fn;

            $disk->putFileAs($folder, $file, $fn);

            return $disk->url($path);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('uploadFile failed: ' . $e->getMessage());
            return null;
        }
    }
}
