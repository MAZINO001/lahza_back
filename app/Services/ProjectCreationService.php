<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\TeamUser;
use App\Models\ProjectAssignment;
use Illuminate\Support\Facades\Mail;

class ProjectCreationService
{
    /**
     * Default task lists for different service types
     *
     * @var array
     */
    protected $defaultTasklist = [];

    public function __construct(){
        // Ensure we always have an array, even if config is null
        $config = config('tasklist');
        $this->defaultTasklist = is_array($config) ? $config : [];
    }

    /**
     * Extract valid project titles from has_projects JSON string
     * 
     * @param string|null $has_projects JSON string containing project titles
     * @return array Array of valid, non-empty project titles
     */
    private function extractValidProjectTitles($has_projects)
    {
        // Return empty array if input is not a string or is empty/whitespace
        if (!is_string($has_projects) || trim($has_projects) === '') {
            return [];
        }

        // Try to decode the JSON
        $decoded = json_decode($has_projects, true);

        // Return empty array if JSON is invalid
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid JSON in has_projects field', [
                'has_projects' => $has_projects,
                'error' => json_last_error_msg()
            ]);
            return [];
        }

        // Handle different possible JSON structures
        if (isset($decoded['title'])) {
            $titles = $decoded['title'];
        } elseif (isset($decoded['titles'])) {
            $titles = $decoded['titles'];
        } elseif (is_array($decoded) && array_keys($decoded) === range(0, count($decoded) - 1)) {
            // Direct array of titles
            $titles = $decoded;
        } else {
            Log::warning('Unexpected JSON structure in has_projects', [
                'has_projects' => $has_projects,
                'decoded' => $decoded
            ]);
            return [];
        }

        // Convert to array if not already
        $titles = is_array($titles) ? $titles : [$titles];

        // Filter out empty or non-string titles, including whitespace-only strings
        $validTitles = array_values(array_filter(
            array_map(function ($title) {
                return is_string($title) ? trim($title) : null;
            }, $titles),
            function ($title) {
                // Must be a non-null string and non-empty after trimming
                return $title !== null && $title !== '';
            }
        ));

        Log::debug('Extracted valid project titles', [
            'original' => $titles,
            'valid' => $validTitles,
            'valid_count' => count($validTitles)
        ]);

        return $validTitles;
    }

    /**
     * Create draft project(s) from a quote or invoice
     * 
     * @param mixed $source Quote or Invoice model
     * @return Project|array|null Returns Project, array of Projects, or null if no projects created
     * @throws \Exception
     */
   /**
 * Create draft project(s) from a quote or invoice
 */
public function createDraftProject($source)
{
    Log::info('Creating draft project from quote/invoice', [
        'source_id' => $source->id,
        'source_type' => get_class($source)
    ]);

    // Prevent duplicates - check both invoice_id and quote_id
    $existingProjects = Project::where(function($query) use ($source) {
        $query->where('invoice_id', $source->id)
              ->orWhere('quote_id', $source->id);
    })->get();

    if ($existingProjects->isNotEmpty()) {
        Log::warning("Draft project(s) already exist, skipping creation.", [
            'source_id' => $source->id,
            'existing_project_ids' => $existingProjects->pluck('id')
        ]);
        return $existingProjects->count() === 1 ? $existingProjects->first() : $existingProjects->all();
    }

    return DB::transaction(function () use ($source) {
        $hasProjectsValue = $source->getAttribute('has_projects');

        // ONLY create projects if has_projects actually has valid titles
        if ($hasProjectsValue !== null && trim($hasProjectsValue) !== '') {
            $projectTitles = $this->extractValidProjectTitles($hasProjectsValue);
            
            if (empty($projectTitles)) {
                Log::info('has_projects exists but has no valid titles → NO project created', [
                    'source_id' => $source->id,
                    'has_projects' => $hasProjectsValue
                ]);
                return null;
            }

            Log::info('Creating draft projects from has_projects titles', [
                'source_id' => $source->id,
                'titles' => $projectTitles
            ]);

            $projects = [];
            foreach ($projectTitles as $title) {
                $project = $this->createSingleProject($source, $title, 'draft');
                if ($project) $projects[] = $project;
            }

            return count($projects) === 1 ? $projects[0] : $projects;
        }

        Log::info('has_projects is null, empty, or missing → NO draft project created', [
            'source_id' => $source->id,
            'has_projects_raw' => $hasProjectsValue
        ]);

        return null;
    });
}
    /**
     * Update project status from draft to pending after payment
     * Called from handleManualPayment or Stripe webhook
     * 
     * @param mixed $invoice
     * @param array $paymentData Additional payment data (optional)
     * @return Project|array|null
     * @throws \Exception
     */
    public function updateProjectAfterPayment($invoice, array $paymentData = [])
    {
        Log::info('Updating project after payment', [
            'invoice_id' => $invoice->id,
            'payment_data' => $paymentData
        ]);

        return DB::transaction(function () use ($invoice, $paymentData) {
            // Find draft projects for this invoice
            $draftProjects = Project::where('invoice_id', $invoice->id)
                ->where('status', 'draft')
                ->get();

            if ($draftProjects->isEmpty()) {
                Log::info('No draft projects found, creating new pending project', [
                    'invoice_id' => $invoice->id
                ]);
                
                // If no draft exists, create a new pending project
                return $this->createProjectForInvoice($invoice);
            }

            // Update all draft projects to pending
            $updatedProjects = [];
            foreach ($draftProjects as $project) {
                $project->update([
                    'status' => 'pending',
                    'description' => 'Project activated after payment.',
                ]);

                Log::info('Updated project status from draft to pending', [
                    'project_id' => $project->id,
                    'invoice_id' => $invoice->id
                ]);

                // Update all tasks to pending (or in_progress if that's the active status)
                // $project->tasks()->update(['status' => 'pending']);

                // Send project creation email
                $this->sendProjectCreationEmail($project, $invoice);

                $updatedProjects[] = $project;
            }

            return count($updatedProjects) === 1 ? $updatedProjects[0] : $updatedProjects;
        });
    }

    /**
     * Cancel project update - revert from pending back to draft
     * Called from cancelManualPayment
     * 
     * @param mixed $invoice
     * @return bool
     */
    public function cancelProjectUpdate($invoice)
    {
        Log::info('Cancelling project update', [
            'invoice_id' => $invoice->id
        ]);

        return DB::transaction(function () use ($invoice) {
            // Find pending projects for this invoice that were recently created
            $pendingProjects = Project::where('invoice_id', $invoice->id)
                ->where('status', 'pending')
                ->get();

            if ($pendingProjects->isEmpty()) {
                Log::warning('No pending projects found to cancel', [
                    'invoice_id' => $invoice->id
                ]);
                return false;
            }

            foreach ($pendingProjects as $project) {
                $project->update([
                    'status' => 'draft',
                    'description' => 'Payment cancelled - project reverted to draft.',
                ]);

                // Revert all tasks back to pending (tasks don't have a draft status)
                // Keep them as pending but the project status indicates it's on hold
                Log::info('Reverted project from pending to draft', [
                    'project_id' => $project->id,
                    'invoice_id' => $invoice->id,
                    'note' => 'Tasks remain in pending status'
                ]);
            }

            return true;
        });
    }

    /**
     * Delete project and all related data (tasks, assignments)
     * Called when deleting invoice or project
     * 
     * @param int $projectId
     * @return bool
     */
    public function deleteProject($projectId)
    {
        Log::info('Deleting project and related data', [
            'project_id' => $projectId
        ]);

        return DB::transaction(function () use ($projectId) {
            $project = Project::find($projectId);

            if (!$project) {
                Log::warning('Project not found for deletion', [
                    'project_id' => $projectId
                ]);
                return false;
            }

            // Delete related tasks
            $deletedTasks = Task::where('project_id', $projectId)->delete();
            Log::info('Deleted project tasks', [
                'project_id' => $projectId,
                'tasks_deleted' => $deletedTasks
            ]);

            // Delete project assignments
            $deletedAssignments = ProjectAssignment::where('project_id', $projectId)->delete();
            Log::info('Deleted project assignments', [
                'project_id' => $projectId,
                'assignments_deleted' => $deletedAssignments
            ]);

            // Delete the project
            $project->delete();
            Log::info('Project deleted successfully', [
                'project_id' => $projectId
            ]);

            return true;
        });
    }

    /**
     * Delete all projects associated with an invoice
     * 
     * @param int $invoiceId
     * @return bool
     */
    public function deleteProjectsByInvoice($invoiceId)
    {
        Log::info('Deleting all projects for invoice', [
            'invoice_id' => $invoiceId
        ]);

        $projects = Project::where('invoice_id', $invoiceId)->get();

        if ($projects->isEmpty()) {
            Log::info('No projects found for invoice', [
                'invoice_id' => $invoiceId
            ]);
            return true;
        }

        foreach ($projects as $project) {
            $this->deleteProject($project->id);
        }

        return true;
    }

    /**
     * Create a project + related tables for an invoice
     * 
     * @param mixed $invoice
     * @return Project|array|null
     * @throws \Exception
     */
    /**
 * Create project(s) after payment or on demand
 */
public function createProjectForInvoice($invoice)
{
    $existingProjects = Project::where('invoice_id', $invoice->id)->get();

    if ($existingProjects->count() > 0) {
        Log::warning("Project already exists, skipping creation.", [
            'invoice_id' => $invoice->id,
            'existing_project_ids' => $existingProjects->pluck('id')
        ]);
        return $existingProjects->count() === 1 ? $existingProjects->first() : $existingProjects->all();
    }

    return DB::transaction(function () use ($invoice) {
        // SAME FIXED LOGIC AS ABOVE
        $hasProjectsValue = $invoice->getAttribute('has_projects');

        if ($hasProjectsValue !== null && trim($hasProjectsValue) !== '') {
            $projectTitles = $this->extractValidProjectTitles($hasProjectsValue);

            if (empty($projectTitles)) {
                Log::info('has_projects exists but no valid titles → NO project created after payment', [
                    'invoice_id' => $invoice->id,
                    'has_projects' => $hasProjectsValue
                ]);
                return null;
            }

            Log::info('Creating pending projects from has_projects after payment', [
                'invoice_id' => $invoice->id,
                'titles' => $projectTitles
            ]);

            $projects = [];
            foreach ($projectTitles as $title) {
                $project = $this->createSingleProject($invoice, $title, 'pending');
                if ($project) $projects[] = $project;
            }

            return count($projects) === 1 ? $projects[0] : $projects;
        }

        // NO MORE DEFAULT PROJECT AFTER PAYMENT
        Log::info('has_projects is null/empty/missing → NO project created after payment', [
            'invoice_id' => $invoice->id
        ]);

        return null; // Victory — no zombie projects
    });
}
    /**
     * Create a single project with tasks
     * 
     * @param mixed $invoice The invoice or quote object
     * @param string|null $customTitle Custom project title
     * @param string $status Project status ('draft' or 'pending')
     * @return Project
     */
   private function createSingleProject($source, $customTitle = null, $status = 'pending')
{
    try {
        $today = now();
        $nextMonday = $today->isMonday() ? $today->copy() : $today->copy()->next('Monday');

        // Determine project name
        $sourceType = get_class($source);
        $isQuote = strpos($sourceType, 'Quote') !== false;
        $isInvoice = strpos($sourceType, 'Invoice') !== false;
        
        $projectName = $customTitle ?? ('Project for ' . ($isQuote ? 'Quote' : 'Invoice') . ' #' . $source->id);

        // Prepare project data
        $projectData = [
            'client_id' => $source->client_id,
            'name' => $projectName,
            'description' => $status === 'draft' 
                ? 'Draft project created from ' . ($isQuote ? 'quote' : 'invoice') . '.' 
                : 'Auto-created project after first payment.',
            'status' => $status,
            'start_date' => $nextMonday,
            'estimated_end_date' => $nextMonday->copy()->addDays(7),
        ];

        // Assign quote_id or invoice_id based on source type
        if ($isQuote) {
            $projectData['quote_id'] = $source->id;
            // If the quote has an associated invoice, also link it
            if (isset($source->invoice_id) && $source->invoice_id) {
                $projectData['invoice_id'] = $source->invoice_id;
            }
        } elseif ($isInvoice) {
            $projectData['invoice_id'] = $source->id;
            // If the invoice has an associated quote, also link it
            if (isset($source->quote_id) && $source->quote_id) {
                $projectData['quote_id'] = $source->quote_id;
            }
        }

        $project = Project::create($projectData);

        Log::info('Successfully created project', [
            'project_id' => $project->id,
            'quote_id' => $project->quote_id ?? null,
            'invoice_id' => $project->invoice_id ?? null,
            'client_id' => $source->client_id,
            'status' => $status,
            'name' => $projectName,
            'source_type' => $sourceType
        ]);

        // Create tasks for the project
        $source_services = $source->services;
        if ($source_services->isEmpty()) {
            Log::warning('No services found for source', [
                'source_id' => $source->id,
                'source_type' => $sourceType
            ]);
            return $project;
        }

        Log::debug('Source services loaded', [
            'count' => $source_services->count(),
            'services' => $source_services->toArray()
        ]);

        // Calculate total number of tasks across all services
        $totalTasks = 0;
        foreach ($source_services as $sourceService) {
            $serviceId = (string)($sourceService->service_id ?? $sourceService->id ?? null);
            
            Log::debug('Processing source service', [
                'source_service' => $sourceService->toArray(),
                'service_id' => $serviceId,
                'service_id_type' => gettype($serviceId),
                'exists_in_tasklist' => !empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]),
                'available_tasklist_keys' => is_array($this->defaultTasklist) ? array_keys($this->defaultTasklist) : []
            ]);

            if (!empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]) && is_array($this->defaultTasklist[$serviceId])) {
                $totalTasks += count($this->defaultTasklist[$serviceId]);
            } else {
                $totalTasks += 1;
            }
        }

        // Calculate percentage per task
        $taskPercentage = $totalTasks > 0 ? round(100 / $totalTasks, 2) : 0;

        // Always create tasks with 'pending' status (tasks table doesn't have 'draft')
        // The project status itself indicates if it's draft or active
        $currentStart = $project->start_date->copy();
        
        foreach ($source_services as $sourceService) {
            $serviceId = (string)($sourceService->service_id ?? $sourceService->id ?? null);
            
            Log::debug('Creating tasks for service', [
                'service_id' => $serviceId,
                'has_default_tasks' => !empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]),
                'available_tasklist_keys' => is_array($this->defaultTasklist) ? array_keys($this->defaultTasklist) : []
            ]);

            if (!empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]) && is_array($this->defaultTasklist[$serviceId])) {
                // Create tasks from default task list for this service
                foreach ($this->defaultTasklist[$serviceId] as $taskData) {
                    $taskEnd = $currentStart->copy()->addDay();
                    
                    Task::create([
                        'title' => $taskData['title'],
                        'project_id' => $project->id,
                        'description' => $taskData['description'],
                        'status' => 'pending', // Always use 'pending' for tasks
                        'start_date' => $currentStart,
                        'end_date' => $taskEnd,
                        'percentage' => $taskPercentage,
                    ]);
                    
                    $currentStart = $taskEnd;
                }
                
                Log::info('Created tasks from default list', [
                    'service_id' => $serviceId,
                    'task_count' => count($this->defaultTasklist[$serviceId])
                ]);
            } else {
                // Create a single generic task if service not in default list
                $taskEnd = $currentStart->copy()->addDay();
                
                Task::create([
                    'title' => $sourceService->service->name ?? 'Service Task',
                    'project_id' => $project->id,
                    'description' => $sourceService->service->description ?? 'Task for service',
                    'status' => 'pending', // Always use 'pending' for tasks
                    'start_date' => $currentStart,
                    'end_date' => $taskEnd,
                    'percentage' => $taskPercentage,
                ]);
                
                $currentStart = $taskEnd;
            }
        }

        Log::info('Successfully created tasks for project', [
            'project_id' => $project->id,
            'total_tasks' => $totalTasks,
            'task_status' => 'pending'
        ]);

        Log::info('Attempting to assign team user to project', [
            'project_id' => $project->id,
        ]);

        // Pick teamuser with oldest last assignment
        $teamUser = TeamUser::withMax('assignments', 'created_at')
            ->orderBy('assignments_max_created_at') // old first, null first
            ->first();

        if ($teamUser) {
            ProjectAssignment::create([
                'project_id' => $project->id,
                'team_id' => $teamUser->id,
                'assigned_by' => 1, // Using a default user ID for now
                'assigned_at' => now(),
            ]);
        }

        // Only send email if project status is 'pending' (payment made)
        if ($status === 'pending') {
            $this->sendProjectCreationEmail($project, $source);
        }

        return $project;

    } catch (\Exception $e) {
        Log::error('Failed to create project', [
            'source_id' => $source->id,
            'source_type' => get_class($source),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    /**
     * Send project creation email
     * 
     * @param Project $project
     * @param mixed $invoice
     * @return void
     */
    private function sendProjectCreationEmail($project, $invoice)
    {
        Log::info('Sending project creation email...', [
            'project_id' => $project->id,
        ]);

        // Prepare email data
        $email = 'mangaka.wir@gmail.com'; // for now use your email
        $assigned_team = ProjectAssignment::with('teamUser')
            ->where('project_id', $project->id)
            ->latest()
            ->first();
        $data = [
            'project' => $project,
            'client' => $invoice->client,
            'client_id'  => $invoice->client_id,
            'invoice' => $invoice,
            'tasks'   => $project->tasks,
            'assigned_team' => $assigned_team,
        ];

        // Send email using your method
         Mail::send('emails.project_created', $data, function ($message) use ($email, $project, $invoice) {
        $message->to($email)
                ->subject('New Project Created - #' . $project->id);
        
        // Add custom header for client_id
        $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-Client-Id', (string)$invoice->client_id);
    });

        Log::info('Project creation email sent successfully.');
    }
}