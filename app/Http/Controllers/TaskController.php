<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;

class TaskController extends Controller
{
    /**
     * Get all tasks for a project.
     */
    public function index(Project $project)
    {
        return $project->tasks()->get();
    }

    /**
     * Create a task inside a project.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'estimated_time' => 'nullable|numeric|min:0',
        ]);

        $task = $project->tasks()->create($validated);

        return response()->json($task, 201);
    }

    /**
     * Get all tasks (global).
     */
    public function allTasks()
    {
        return Task::all();
    }

    /**
     * Update a task.
     */
    public function update(Request $request, Project $project, Task $task)
    {
        // ensure task belongs to the project
        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Task does not belong to this project'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'estimated_time' => 'nullable|numeric|min:0',
        ]);

        $task->update($validated);

        return response()->json($task);
    }

    /**
     * Delete a task.
     */
    public function destroy(Project $project, Task $task)
    {
        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Task does not belong to this project'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }
}
