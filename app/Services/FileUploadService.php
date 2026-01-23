<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class FileUploadService
{
    /**
     * @param UploadedFile|UploadedFile[] $files
     */
    public function upload(
        $files,
        Model $model,
        string $type,
        string $folder = 'files',
        string $disk = 'public'
    ): void {
        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            $path = $file->store($folder, $disk);

            $model->files()->create([
                'path' => $path,
                'type' => $type,
                'disk' => $disk, 
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function deleteFile(Model $fileRecord): void
    {
        Storage::disk($fileRecord->disk)->delete($fileRecord->path);
        $fileRecord->delete();
    }
}