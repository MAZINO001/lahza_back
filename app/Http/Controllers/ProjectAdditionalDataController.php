<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProjectAdditionalData;
class ProjectAdditionalDataController extends Controller
{
     public function showByProject($project_id)
    {
        $data = ProjectAdditionalData::where('project_id', $project_id)->first();

        if (!$data) {
            return response()->json(['message' => 'Additional data not found'], 404);
        }

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
            'client_id'  => 'required|exists:clients,id',
            'host_acc' => 'nullable|string',
            'website_acc' => 'nullable|string',
            'social_media' => 'nullable|string',
            'media_files' => 'nullable|string',
            'specification_file' => 'nullable|string',
            'logo' => 'nullable|string',
            'other' => 'nullable|string',
        ]);

        $data = ProjectAdditionalData::create($validated);

        return response()->json($data, 201);
    }

    /**
     * Update existing Additional Data
     * PUT /additional-data/{id}
     */
    public function update(Request $request, $id)
    {
        $data = ProjectAdditionalData::find($id);

        if (!$data) {
            return response()->json(['message' => 'Additional data not found'], 404);
        }

        $validated = $request->validate([
            'host_acc' => 'nullable|string',
            'website_acc' => 'nullable|string',
            'social_media' => 'nullable|string',
            'media_files' => 'nullable|string',
            'specification_file' => 'nullable|string',
            'logo' => 'nullable|string',
            'other' => 'nullable|string',
        ]);

        $data->update($validated);

        return response()->json($data);
    }

    /**
     * Delete Additional Data
     * DELETE /additional-data/{id}
     */
    public function destroy($id)
    {
        $data = ProjectAdditionalData::find($id);

        if (!$data) {
            return response()->json(['message' => 'Additional data not found'], 404);
        }

        $data->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
