<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Project;
class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Project::with('invoices')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'start_date' => 'required|date',
            'estimated_end_date' => 'required|date|after_or_equal:start_date',
            'quote_id' => 'nullable|exists:quotes,id',
            'invoice_ids' => 'nullable|array',
            'invoice_ids.*' => 'exists:invoices,id'
        ]);

        $project = Project::create($validated);
        
        if (isset($validated['invoice_ids']) && !empty($validated['invoice_ids'])) {
            $project->invoices()->sync($validated['invoice_ids']);
        }

        return response()->json($project->load('invoices'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return $project;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'client_id' => 'sometimes|required|exists:clients,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,in_progress,completed,cancelled',
            'start_date' => 'sometimes|required|date',
            'estimated_end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'quote_id' => 'nullable|exists:quotes,id',
            'invoice_ids' => 'nullable|array',
            'invoice_ids.*' => 'exists:invoices,id'
        ]);

        $project->update($validated);
        
        if (array_key_exists('invoice_ids', $validated)) {
            $project->invoices()->sync($validated['invoice_ids']);
        }

        return response()->json($project->load('invoices'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        // Detach all invoices before deleting the project
        $project->invoices()->detach();
        
        // Delete the project
        $project->delete();
        
        return response()->json(null, 204);
    }
}
