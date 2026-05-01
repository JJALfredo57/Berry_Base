<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait UploadsFiles
{
    protected function uploadFile(UploadedFile $file, string $folder): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $fn  = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $disk = config('filesystems.upload_disk', 'public');
        Storage::disk($disk)->putFileAs($folder, $file, $fn, 'public');
        return Storage::disk($disk)->url($folder . '/' . $fn);
    }
}
