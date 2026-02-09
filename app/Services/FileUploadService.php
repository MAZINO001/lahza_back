<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /**
     * Manage reusable attachments (create, update, delete all-in-one)
     * 
     * @param array $data Contains 'new_files', 'delete_file_ids'
     * @param string $type The file type (e.g., 'email_attachment', 'template_attachment')
     * @param string $folder Storage folder
     * @param string $disk Storage disk
     * @return array Created and kept file IDs
     */
   public function manageReusableAttachments(
    array $data,
    string $type = 'reusable_attachment',
    string $folder = 'attachments',
    string $disk = 'public'
): array {
    $createdFileIds = [];

    DB::beginTransaction();
    
    try {
        // 1. DELETE specified files FIRST
        if (!empty($data['delete_file_ids'])) {
            $deleteIds = is_array($data['delete_file_ids']) 
                ? $data['delete_file_ids'] 
                : [$data['delete_file_ids']];

            $user = Auth::user();
            $query = File::whereIn('id', $deleteIds)
                ->where('type', $type)
                ->whereNull('fileable_id');

            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            $filesToDelete = $query->get();

            foreach ($filesToDelete as $file) {
                Storage::disk($file->disk)->delete($file->path);
                $file->delete();
            }
        }

        // 2. Handle NEW file uploads
        if (!empty($data['new_files'])) {
            $newFiles = is_array($data['new_files']) ? $data['new_files'] : [$data['new_files']];
            
            foreach ($newFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $originalName = $file->getClientOriginalName();
                    $path = $file->store($folder, $disk);
                    $size = $file->getSize();
                    $mimeType = $file->getMimeType();

                    $fileRecord = File::create([
                        'path' => $path,
                        'type' => $type,
                        'disk' => $disk,
                        'user_id' => Auth::id(),
                        'original_name' => $originalName,
                        'size' => $size,
                        'mime_type' => $mimeType,
                        'fileable_type' => 'quote/invoice',
                        'fileable_id' => null,
                    ]);

                    $createdFileIds[] = $fileRecord->id;
                }
            }
        }

        DB::commit();

        // 3. Get all remaining files for this type
        $allFiles = $this->getReusableAttachments($type);

        return [
            'created' => $createdFileIds,
            'all_files' => $allFiles,
        ];

    } catch (\Exception $e) {
        DB::rollBack();
        
        // Clean up any uploaded files if transaction fails
        foreach ($createdFileIds as $id) {
            $file = File::find($id);
            if ($file) {
                Storage::disk($file->disk)->delete($file->path);
                $file->forceDelete();
            }
        }
        
        throw $e;
    }
}

    /**
     * Get reusable attachments for the authenticated user
     * 
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getReusableAttachments(string $type = 'reusable_attachment')
    {
        $user = Auth::user();
        
        $query = File::where('type', $type)
        ->whereNull('fileable_id');
        
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        return $query->latest()->get();
    }

    /**
     * Attach reusable files to a model (for invoices/quotes)
     * 
     * @param array $fileIds
     * @param Model $model
     */
    public function attachReusableFiles(array $fileIds, Model $model): void
    {
        $user = Auth::user();
        
        $query = File::whereIn('id', $fileIds);
        
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $files = $query->get();

        foreach ($files as $file) {
            // Create a copy of the reusable file attached to this specific model
            $model->files()->create([
                'path' => $file->path,
                'type' => $file->type,
                'disk' => $file->disk,
                'user_id' => $file->user_id,
                'original_name' => $file->original_name,
                'size' => $file->size,
                'mime_type' => $file->mime_type,
            ]);
        }
    }
}