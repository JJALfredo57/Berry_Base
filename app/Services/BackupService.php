<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupService
{
    public function backupsDir(): string
    {
        return storage_path('app/backups');
    }

    public function ensureBackupsDir(): void
    {
        $dir = $this->backupsDir();

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Backup folder could not be created.');
        }

        if (!is_writable($dir)) {
            throw new \RuntimeException('Backup folder is not writable.');
        }
    }

    public function listBackups(): array
    {
        $dir = $this->backupsDir();
        if (!is_dir($dir)) {
            return [];
        }

        try {
            $files = array_filter(array_map(
                fn ($file) => $file->getPathname(),
                File::files($dir)
            ), function (string $path): bool {
                return is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['sql', 'zip'], true);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to list backup files: ' . $e->getMessage());
            return [];
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(function (string $path): array {
            return [
                'path' => $path,
                'name' => basename($path),
                'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ?: time(),
                'is_restorable' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'sql',
            ];
        }, $files);
    }

    public function createDatabaseBackup(string $label = 'manual'): array
    {
        $this->ensureBackupsDir();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database") ?: $connection;
        $content = CakeshopHelper::exportSql((string) $database);

        if (trim($content) === '') {
            throw new \RuntimeException('Database export was empty.');
        }

        $fname = 'berrybase_' . $label . '_' . $connection . '_' . date('Y-m-d_H-i-s') . '.sql';
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $fname;

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write backup file.');
        }

        return $this->fileInfo($path);
    }

    public function createFullBackup(string $label = 'manual'): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Full backup requires PHP ZipArchive to be enabled.');
        }

        $this->ensureBackupsDir();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database") ?: $connection;
        $sql = CakeshopHelper::exportSql((string) $database);

        if (trim($sql) === '') {
            throw new \RuntimeException('Database export was empty.');
        }

        $fname = 'berrybase_full_' . $label . '_' . $connection . '_' . date('Y-m-d_H-i-s') . '.zip';
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $fname;
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create full backup archive.');
        }

        $zip->addFromString('database.sql', $sql);
        $this->addDirectoryToZip($zip, storage_path('app/public'), 'uploads');
        $zip->close();

        return $this->fileInfo($path);
    }

    public function restoreSqlBackup(string $file): array
    {
        $path = $this->resolveBackupPath($file);

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'sql') {
            throw new \RuntimeException('Only SQL backup files can be restored.');
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('Backup file is empty or unreadable.');
        }

        $safety = $this->createDatabaseBackup('pre_restore');

        DB::transaction(function () use ($sql) {
            DB::unprepared($sql);
        });

        return [
            'restored' => $this->fileInfo($path),
            'safety' => $safety,
        ];
    }

    public function storeUploadedSql(UploadedFile $upload): array
    {
        $this->ensureBackupsDir();

        $original = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $original = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'uploaded_backup';
        $fname = 'uploaded_' . $original . '_' . date('Y-m-d_H-i-s') . '.sql';
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $fname;

        if (!$upload->isValid() || strtolower($upload->getClientOriginalExtension()) !== 'sql') {
            throw new \RuntimeException('Please upload a valid .sql backup file.');
        }

        $upload->move($this->backupsDir(), $fname);

        if (!is_file($path) || filesize($path) <= 0) {
            throw new \RuntimeException('Uploaded backup is empty or could not be saved.');
        }

        return $this->fileInfo($path);
    }

    public function deleteBackup(string $file): array
    {
        $path = $this->resolveBackupPath($file);
        $info = $this->fileInfo($path);

        if (!unlink($path)) {
            throw new \RuntimeException('Backup file could not be deleted.');
        }

        return $info;
    }

    public function resolveBackupPath(string $file): string
    {
        $file = preg_replace('/[^A-Za-z0-9._-]/', '', $file);
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $file;
        $realDir = realpath($this->backupsDir());
        $realPath = realpath($path);

        if (!$file || !$realDir || !$realPath || !str_starts_with($realPath, $realDir) || !is_file($realPath)) {
            throw new \RuntimeException('Backup file not found.');
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'zip'], true)) {
            throw new \RuntimeException('Invalid backup file type.');
        }

        return $realPath;
    }

    public function pruneOldBackups(int $keep): int
    {
        $keep = max(1, min(100, $keep));
        $files = $this->listBackups();
        $deleted = 0;

        foreach (array_slice($files, $keep) as $file) {
            try {
                if (is_file($file['path']) && unlink($file['path'])) {
                    $deleted++;
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to prune old backup: ' . $e->getMessage(), ['file' => $file['name'] ?? null]);
            }
        }

        return $deleted;
    }

    public function fileInfo(string $path): array
    {
        return [
            'path' => $path,
            'name' => basename($path),
            'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'size' => filesize($path) ?: 0,
            'modified_at' => filemtime($path) ?: time(),
        ];
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = File::allFiles($dir);
        foreach ($files as $file) {
            if ($file->getSize() <= 0) {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getRealPath(), trim($prefix, '/') . '/' . $relative);
        }
    }
}
