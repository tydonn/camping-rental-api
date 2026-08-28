<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PhotoStorageService
{
    /**
     * Simpan file foto ke disk public pada folder tertentu,
     * lalu hapus file lama jika ada (dipakai saat mengganti foto).
     */
    public function store(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'public');
    }

    public function replace(UploadedFile $file, string $folder, ?string $oldPath): string
    {
        $this->delete($oldPath);

        return $this->store($file, $folder);
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
