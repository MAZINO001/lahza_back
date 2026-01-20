<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use App\Models\ProjectAdditionalData;
use App\Models\Project;
class ProjectAdditionalDataController extends Controller
{
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
        // 'logo' => 'nullable|file|mimes:jpg,jpeg,png,svg',
        'logo' => 'nullable|file',
        'specification_file.*' => 'nullable|file',
        'media_files.*' => 'nullable|file',
        'other.*' => 'nullable|file',
    ]);

    $project = Project::findOrFail($validated['project_id']);

    // Pass an array: [The Class the policy belongs to, The specific project to check]
    $this->authorize('create', [ProjectAdditionalData::class, $project]);    // Handle single files
    if ($request->hasFile('logo')) {
        $validated['logo'] = $request->file('logo')->store('additionalData/logo', 'public');
    }

    if ($request->hasFile('specification_file')) {
        $specification_file = [];
        foreach ($request->file('specification_file') as $file) {
            $specification_file[] = $file->store('additionalData/specification_file', 'public');
        }
        $validated['specification_file'] = json_encode($specification_file);
    }

    // Handle multiple files
    if ($request->hasFile('media_files')) {
        $mediaFiles = [];
        foreach ($request->file('media_files') as $file) {
            $mediaFiles[] = $file->store('additionalData/media_files', 'public');
        }
        $validated['media_files'] = json_encode($mediaFiles); // store as JSON
    }

    if ($request->hasFile('other')) {
        $otherFiles = [];
        foreach ($request->file('other') as $file) {
            $otherFiles[] = $file->store('additionalData/other_files', 'public');
        }
        $validated['other'] = json_encode($otherFiles);
    }

    $data = ProjectAdditionalData::create($validated);

    return response()->json($data, 201);
}

public function update(Request $request, $id)
{
    Log::info("UPDATE REQUEST RECEIVED", [
        'id' => $id,
        'all_request' => $request->all()
    ]);

    // Find record
    $data = ProjectAdditionalData::find($id);

    $this->authorize('update',$data);

    if (!$data) {
        Log::error("DATA NOT FOUND", ['id' => $id]);
        return response()->json(['message' => 'Additional data not found'], 404);
    }

    Log::info("DATA FOUND", ['data' => $data]);

    try {
        $validated = $request->validate([
            'project_id' => 'sometimes|exists:projects,id',
            'client_id'  => 'sometimes|exists:clients,id',
            'host_acc' => 'nullable|string',
            'website_acc' => 'nullable|string',
            'social_media' => 'nullable|string',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,svg',
            'specification_file.*' => 'nullable|file',
            'media_files.*' => 'nullable|file',
            'other.*' => 'nullable|file',
        ]);

        Log::info("VALIDATION PASSED", ['validated' => $validated]);

    } catch (\Exception $e) {
        Log::error("VALIDATION FAILED", ['error' => $e->getMessage()]);
        return response()->json(['message' => $e->getMessage()], 422);
    }

    /* ---------------------- LOGO ----------------------- */
    if ($request->hasFile('logo')) {
        Log::info("LOGO UPLOAD DETECTED");

        if ($data->logo && Storage::disk('public')->exists($data->logo)) {
            Storage::disk('public')->delete($data->logo);
            Log::info("OLD LOGO DELETED", ['logo' => $data->logo]);
        }

        $validated['logo'] = $request->file('logo')->store('additionalData/logo', 'public');
        Log::info("NEW LOGO STORED", ['path' => $validated['logo']]);
    }

    /* ---------------------- SPECIFICATION FILES ----------------------- */
    if ($request->hasFile('specification_file')) {
        Log::info("SPECIFICATION FILES UPLOAD DETECTED");

        $oldFiles = json_decode($data->specification_file, true) ?? [];
        foreach ($oldFiles as $old) {
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }
        Log::info("OLD SPECIFICATION FILES DELETED", ['files' => $oldFiles]);

        $newFiles = [];
        foreach ($request->file('specification_file') as $file) {
            $newFiles[] = $file->store('additionalData/specification_file', 'public');
        }

        $validated['specification_file'] = json_encode($newFiles);
        Log::info("NEW SPECIFICATION FILES STORED", ['files' => $newFiles]);
    }

    /* ---------------------- MEDIA FILES ----------------------- */
    if ($request->hasFile('media_files')) {
        Log::info("MEDIA FILES UPLOAD DETECTED");

        $oldFiles = json_decode($data->media_files, true) ?? [];
        foreach ($oldFiles as $old) {
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }
        Log::info("OLD MEDIA FILES DELETED", ['files' => $oldFiles]);

        $newFiles = [];
        foreach ($request->file('media_files') as $file) {
            $newFiles[] = $file->store('additionalData/media_files', 'public');
        }

        Log::info("NEW MEDIA FILES STORED", ['files' => $newFiles]);

        $validated['media_files'] = json_encode($newFiles);
    }

    /* ---------------------- OTHER FILES ----------------------- */
    if ($request->hasFile('other')) {
        Log::info("OTHER FILES UPLOAD DETECTED");

        $oldFiles = json_decode($data->other, true) ?? [];
        foreach ($oldFiles as $old) {
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }
        Log::info("OLD OTHER FILES DELETED", ['files' => $oldFiles]);

        $newFiles = [];
        foreach ($request->file('other') as $file) {
            $newFiles[] = $file->store('additionalData/other_files', 'public');
        }

        Log::info("NEW OTHER FILES STORED", ['files' => $newFiles]);

        $validated['other'] = json_encode($newFiles);
    }

    /* ---------------------- UPDATE MODEL ----------------------- */
    Log::info("FINAL VALIDATED DATA", $validated);

    try {
        $data->update($validated);
        Log::info("DATA UPDATED SUCCESSFULLY", ['new_data' => $data]);
    } catch (\Exception $e) {
        Log::error("UPDATE FAILED", ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Update failed', 'error' => $e->getMessage()], 500);
    }

    return response()->json($data);
}



    /**
     * Delete Additional Data
     * DELETE /additional-data/{id}
     */

public function destroy($id)
{
    $data = ProjectAdditionalData::find($id);
$this->authorize('delete');
    if (!$data) {
        return response()->json(['message' => 'Additional data not found'], 404);
    }

    // Create a unique archive folder, e.g., "archive/project_5/2025-12-10_1530"
    $timestamp = now()->format('Y-m-d_His');
    $archiveFolder = "archive/project_{$data->project_id}/{$timestamp}";

    // Helper function to move a single file
    $moveFile = function ($filePath, $folder) {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            $filename = basename($filePath);
            $newPath = $folder . '/' . $filename;
            Storage::disk('public')->move($filePath, $newPath);
        }
    };

    // Move single files
    $moveFile($data->logo, $archiveFolder . '/logo');

    // Move multiple files (JSON encoded)
        $specification_file = json_decode($data->specification_file, true) ?? [];
    foreach ($specification_file as $file) {
        $moveFile($file, $archiveFolder . '/specification_file');
    }

    $mediaFiles = json_decode($data->media_files, true) ?? [];
    foreach ($mediaFiles as $file) {
        $moveFile($file, $archiveFolder . '/media_files');
    }

    $otherFiles = json_decode($data->other, true) ?? [];
    foreach ($otherFiles as $file) {
        $moveFile($file, $archiveFolder . '/other_files');
    }

    // Finally, delete the database record
    $data->delete();

    return response()->json(['message' => 'Deleted successfully and archived files.']);
}

}
