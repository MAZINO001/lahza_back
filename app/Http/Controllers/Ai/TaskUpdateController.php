<?php

namespace App\Http\Controllers\ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Services\ProjectTaskService;
use Illuminate\Support\Facades\Log;

class TaskUpdateController extends Controller
{
    protected $service;

    public function __construct(ProjectTaskService $service)
    {
        $this->service = $service;
    }

    public function generate(Project $project)
    {
        try {
            $aiRun = $this->service->generateProjectDraft($project);
            
            return response()->json([
                'success' => true,
                'message' => 'Draft generated successfully',
                'ai_run_id' => $aiRun->id,
                'draft' => $aiRun->output_data
            ]);
            
        } catch (\Exception $e) {
            Log::error('Task generation failed:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, $aiRunId)
    {
        try {
            $request->validate([
                'tasks' => 'required|array',
                'tasks.*.title' => 'required|string',
                'tasks.*.description' => 'nullable|string',
                'tasks.*.hours' => 'required|numeric|min:0'
            ]);
            
            $this->service->convertDraftToTasks($aiRunId, $request->tasks);

            return response()->json([
                'success' => true,
                'message' => 'Tasks created successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tasks: ' . $e->getMessage()
            ], 500);
        }
    }
}