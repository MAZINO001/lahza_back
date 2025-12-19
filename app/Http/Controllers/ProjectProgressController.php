<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
class ProjectProgressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $tasks_count = $project->tasks()->count();
        if (!$project->progress){
            return response()->json([
                'message' => 'Project progress not avaible',
                'accumlated_percentage' => 0,
                'tasks_count' => $tasks_count,
                'done_tasks_count' => 0,
            ], 404);
        }
        return $project->progress;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
