<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;
use App\Models\ProjectProgress;
use Illuminate\Support\Facades\Auth;

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
     * Supports both single task object and array of tasks.
     */
    public function store(Request $request, Project $project)
    {
        $data = $request->all();
        
        // Check if request is an array (bulk creation)
        if (isset($data[0]) && is_array($data[0])) {
            // Bulk creation: array of tasks
            $tasks = [];
            
            // First, create all tasks without percentages
            foreach ($data as $taskData) {
                $validated = validator($taskData, [
                    'title' => 'required|string',
                    'description' => 'nullable|string',
                    'estimated_time' => 'nullable|numeric|min:0',
                ])->validate();
                
                // Don't allow setting percentage directly
                unset($validated['percentage']);
                
                $tasks[] = $project->tasks()->create($validated);
            }
            
            // After creating all tasks, update percentages for all tasks in the project
            $this->updateTaskPercentages($project);
            
            return response()->json([
                'success' => true,
                'message' => count($tasks) . ' task(s) created',
                'data' => $project->tasks()->get()
            ], 201);
        } else {
            // Single task creation
            $validated = $request->validate([
                'title' => 'required|string',
                'description' => 'nullable|string',
                'estimated_time' => 'nullable|numeric|min:0',
            ]);

            // Don't allow setting percentage directly
            unset($validated['percentage']);
            
            $task = $project->tasks()->create($validated);
            
            // After creating task, update percentages for all tasks in the project
            $this->updateTaskPercentages($project);

            return response()->json($task->fresh(), 201);
        }
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
            'estimated_time' => 'nullable|numeric|min:0',
            // Removed percentage from update as it should only be updated via updateStatus
        ]);

        $task->update($validated);

        return response()->json($task->fresh());
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
        
        // After deleting task, update percentages for remaining tasks
        $this->updateTaskPercentages($project);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted',
            'data' => $project->tasks()->get()
        ]);
    }
    public function updateStatus(Task $task){
        if($task->status == 'pending'){
            $task->update([
                'status' => 'done',
                'percentage' => 100
            ]);
        } else if($task->status == 'done'){
            $task->update([
                'status' => 'pending',
                'percentage' => 0
            ]);
        }
        
        // Update percentages for all tasks in the project
        $this->updateTaskPercentages($task->project);
        
        return response()->json([
            'success' => true,
            'message' => 'Task status updated',
            'data' => $task->project->progress->fresh()
        ]);
    }
    
    /**
     * Update task percentages for all tasks in a project
     * Ensures the total percentage is distributed evenly among all tasks
     */
    private function updateTaskPercentages(Project $project)
    {
        $tasks = $project->tasks()->get();
        $taskCount = $tasks->count();
        
        if ($taskCount === 0) {
            return response()->json([
                'error' => 'No tasks found for this project'
            ], 404);
        }
        
        $percentagePerTask = 100 / $taskCount;
        
        // Update all tasks with the new percentage
        foreach ($tasks as $task) {
            // If task is done, keep it at 100%, otherwise set to equal share            
            $task->update([
                'percentage' => $percentagePerTask
            ]);
        }
        // $tasks->refresh();
        $donePercentage = $tasks->where('status', 'done')->sum('percentage');
    ProjectProgress::updateOrCreate(
    [
        'project_id' => $project->id,
    ],
    [
        'accumlated_percentage' => $donePercentage,
        // 'team_id'=>Auth::user()->id,
        'team_id'=>'1',
    ]
    );
    
    }
}
