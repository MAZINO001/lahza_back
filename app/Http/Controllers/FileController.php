<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Quotes;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'type'          => 'nullable|string|max:255',
            'fileable_type' => 'nullable|string|max:255',
            'fileable_id'   => 'nullable|integer',
            'disk'          => 'nullable|string|max:255',
            'with_trashed'  => 'nullable|boolean',
        ]);

        $query = File::query();
        $user = Auth::user();

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        } else {
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $validated['type']);
        }

        if ($request->filled('fileable_type')) {
            $type = $validated['fileable_type'];
            $query->where('fileable_type', str_contains($type, '\\') ? $type : 'App\\Models\\' . ucfirst($type));
        }

        if ($request->filled('fileable_id')) {
            $query->where('fileable_id', $validated['fileable_id']);
        }

        if ($request->filled('disk')) {
            $query->where('disk', $validated['disk']);
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $files = $query->latest()->get();

        return response()->json($files);
    }

    public function download($path)
    {
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->file($fullPath);
    }

    /**
     * Manage reusable attachments (create/update/delete all-in-one)
     */
    public function manageAttachments(Request $request)
    {
        $validated = $request->validate([
            'new_files.*' => 'nullable|file|max:10240', // 10MB max per file
            'delete_file_ids' => 'nullable|array',
            'delete_file_ids.*' => 'integer|exists:files,id',
            'type' => 'nullable|string|max:255',
        ]);

        try {
            $type = $validated['type'] ?? 'reusable_attachment';
            
            $result = $this->fileUploadService->manageReusableAttachments([
                'new_files' => $request->file('new_files'),
                'delete_file_ids' => $validated['delete_file_ids'] ?? [],
            ], $type);

            return response()->json([
                'message' => 'Attachments managed successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to manage attachments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all reusable attachments
     */
    public function getAttachments()
    {
      
        $type =  'reusable_attachment';
        
        $attachments = $this->fileUploadService->getReusableAttachments($type);

        return response()->json([
            'data' => $attachments,
        ], 200);
    }
}