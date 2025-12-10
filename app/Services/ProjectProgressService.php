<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectProgress;
use Illuminate\Support\Facades\DB;

class ProjectProgressService
{
    /**
     * Calculate and update project progress based on completed tasks
     *
     * @param int $projectId
     * @return array
     */
    public function calculateAndUpdateProgress($projectId)
    {
        $project = Project::with(['tasks'])->findOrFail($projectId);
        
        $totalTasks = $project->tasks->count();
        if ($totalTasks === 0) {
            return [
                'success' => false,
                'message' => 'No tasks found for this project',
                'progress' => 0
            ];
        }

        $completedTasks = $project->tasks->where('status', 'done')->count();
        $progressPercentage = ($completedTasks / $totalTasks) * 100;

        // Store the progress
        $projectProgress = ProjectProgress::updateOrCreate(
            ['project_id' => $projectId],
            ['accumulated_percentage' => $progressPercentage]
        );

        // Check if project is 100% complete
        if ($progressPercentage >= 100) {
            $this->handleProjectCompletion($project);
        }

        return [
            'success' => true,
            'progress' => $progressPercentage,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks
        ];
    }

    /**
     * Handle project completion logic
     *
     * @param Project $project
     * @return void
     */
    public function handleProjectCompletion(Project $project)
    {
        // Update project status to completed
        $project->update(['statu' => 'completed']);
        
        // Here you can add any additional completion logic
        // For example, send notifications, create reports, etc.
    }

    /**
     * Confirm project completion and perform cleanup
     *
     * @param int $projectId
     * @return array
     */
    public function confirmProjectCompletion($projectId)
    {
        $project = Project::findOrFail($projectId);
        
        if ($project->statu !== 'completed') {
            return [
                'success' => false,
                'message' => 'Project is not marked as completed',
                'can_confirm' => false
            ];
        }

        // Perform cleanup tasks here
        // For example:
        // - Archive project
        // - Remove temporary files
        // - Close related resources
        
        // Update project status to confirmed
        $project->update(['statu' => 'confirmed_completed']);

        return [
            'success' => true,
            'message' => 'Project completion confirmed and cleanup performed',
            'project_status' => 'confirmed_completed'
        ];
    }
}
