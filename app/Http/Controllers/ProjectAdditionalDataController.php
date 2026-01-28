<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use App\Models\ProjectAdditionalData;
use App\Models\Project;
use App\Services\FileUploadService;
class ProjectAdditionalDataController extends Controller
{
    protected $fileUploadService;
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
     public function showByProject($project_id)
    {
        $data = ProjectAdditionalData::where('project_id', $project_id)->first();
        if (!$data) {
            return response()->json(['message' => 'Additional data not found'], 404);
        }
        $this->authorize('view', $data);
        return response()->json($data);
    }

    /**
     * Store new Project Additional Data
     * POST /additional-data
     */
public function store(Request $request)
{
    $validated = $request->validate([
        'project_id' => 'required|exists:projects,id',
        'host_acc' => 'nullable|string',
        'website_acc' => 'nullable|string',
        'social_media' => 'nullable|string',

        'logo' => 'nullable|array',
        'logo.*' => 'file',

        'specification_file'   => 'nullable|array',
        'specification_file.*' => 'file',

        'media_files'   => 'nullable|array',
        'media_files.*' => 'file',

        'other'   => 'nullable|array',
        'other.*' => 'file',

    ]);

    $project = Project::findOrFail($validated['project_id']);
    $this->authorize('create', [ProjectAdditionalData::class, $project]);

    // Create metadata only (NO FILE PATHS)
    $data = ProjectAdditionalData::updateOrCreate(
    ['project_id' => $validated['project_id']], // lookup key
    [
        'host_acc' => $validated['host_acc'] ?? null,
        'website_acc' => $validated['website_acc'] ?? null,
        'social_media' => $validated['social_media'] ?? null,
    ]
);


    // 🧠 File handling via service
    if ($request->hasFile('logo')) {
          $this->ensureFileLimit(
        $data,
        'logo',
        count($request->file('logo'))
    );
        $this->fileUploadService->upload(
            $request->file('logo'),
            $data,
            'logo',
            'additionalData/logo'
        );
    }

    if ($request->hasFile('specification_file')) {
          $this->ensureFileLimit(
        $data,
        'specification_file',
        count($request->file('specification_file'))
    );
        $this->fileUploadService->upload(
            $request->file('specification_file'),
            $data,
            'specification_file',
            'additionalData/specification_file'
        );
    }

    if ($request->hasFile('media_files')) {
          $this->ensureFileLimit(
        $data,
        'media_files',
        count($request->file('media_files'))
    );
        $this->fileUploadService->upload(
            $request->file('media_files'),
            $data,
            'media_files',
            'additionalData/media_files'
        );
    }

    if ($request->hasFile('other')) {
          $this->ensureFileLimit(
        $data,
        'other',
        count($request->file('other'))
    );
        $this->fileUploadService->upload(
            $request->file('other'),
            $data,
            'other',
            'additionalData/other_files'
        );
    }

    return response()->json($data->load('files'), 201);
}


public function update(Request $request, $id)
{
    $data = ProjectAdditionalData::findOrFail($id);
    $this->authorize('update', $data);

    $validated = $request->validate([
        'host_acc' => 'nullable|string',
        'website_acc' => 'nullable|string',
        'social_media' => 'nullable|string',
        'logo' => 'nullable|array',
        'specification_file' => 'nullable|array',
        'media_files' => 'nullable|array',
        'other' => 'nullable|array',
    ]);

    // 1. Update ONLY the text metadata columns
    $data->update($request->only(['host_acc', 'website_acc', 'social_media']));

    // 2. Helper: delete old files by type
    $deleteByType = function ($type) use ($data) {
        $data->files()
            ->where('type', $type)
            ->get()
            ->each(fn ($file) => $this->fileUploadService->deleteFile($file));
    };

    // 3. Helper: check if field should be deleted
    // Returns true if field contains ["[]"] or is empty array []
    $shouldDeleteField = function ($fieldValue) {
        if (!is_array($fieldValue)) {
            return false;
        }

        // Check if empty array
        if (empty($fieldValue)) {
            return true;
        }

        // Check if array contains only the string "[]"
        if (count($fieldValue) === 1 && $fieldValue[0] === '[]') {
            return true;
        }

        return false;
    };

    // 4. Handle File Uploads
    $fileFields = [
        'logo' => 'additionalData/logo',
        'specification_file' => 'additionalData/specification_file',
        'media_files' => 'additionalData/media_files',
        'other' => 'additionalData/other_files'
    ];

    foreach ($fileFields as $field => $path) {
        if ($request->has($field)) {
            $fieldValue = $request->input($field);

            // Check if should delete (empty array or ["[]"])
            if ($shouldDeleteField($fieldValue)) {
                $deleteByType($field);
            }
            // Otherwise, if has actual files, upload them
            elseif ($request->hasFile($field)) {
                $deleteByType($field);
                $this->fileUploadService->upload(
                    $request->file($field),
                    $data,
                    $field,
                    $path
                );
            }
        }
    }

    return response()->json($data->load('files'));
}

public function destroy($id)
{
    $data = ProjectAdditionalData::findOrFail($id);
    $this->authorize('delete', $data);

    $data->files->each(fn ($file) =>
        $this->fileUploadService->deleteFile($file)
    );

    $data->delete();

    return response()->json(['message' => 'Deleted successfully']);
}
private function ensureFileLimit(
    ProjectAdditionalData $data,
    string $type,
    int $incomingCount,
    int $limit = 10
): void {
    $existingCount = $data->files()
        ->where('type', $type)
        ->count();

    if ($existingCount + $incomingCount > $limit) {
        abort(422, "Maximum {$limit} files allowed for {$type}");
    }
}

}
