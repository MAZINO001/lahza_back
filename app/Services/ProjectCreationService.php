<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\TeamUser;
use App\Models\ProjectAssignment;
use App\Services\ActivityLoggerService;
use Illuminate\Support\Facades\Mail;
use App\Mail\ProjectCreatedMail;
use App\Mail\SendReportMail;

class ProjectCreationService
{
    /**
     * Default task lists for different service types
     *
     * @var array
     */
    protected $defaultTasklist = [];
    protected $activityLogger;

    public function __construct(ActivityLoggerService $activityLogger)
    {
        $config = config('tasklist');
        $this->defaultTasklist = is_array($config) ? $config : [];
        $this->activityLogger = $activityLogger;
    }

    /**
     * Extract valid project titles from has_projects JSON string
     */
    private function extractValidProjectTitles($has_projects)
    {
        if (!is_string($has_projects) || trim($has_projects) === '') {
            return [];
        }

        $decoded = json_decode($has_projects, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid JSON in has_projects field', [
                'has_projects' => $has_projects,
                'error' => json_last_error_msg()
            ]);
            return [];
        }

        if (isset($decoded['title'])) {
            $titles = $decoded['title'];
        } elseif (isset($decoded['titles'])) {
            $titles = $decoded['titles'];
        } elseif (is_array($decoded) && array_keys($decoded) === range(0, count($decoded) - 1)) {
            $titles = $decoded;
        } else {
            Log::warning('Unexpected JSON structure in has_projects', [
                'has_projects' => $has_projects,
                'decoded' => $decoded
            ]);
            return [];
        }

        $titles = is_array($titles) ? $titles : [$titles];

        $validTitles = array_values(array_filter(
            array_map(function ($title) {
                return is_string($title) ? trim($title) : null;
            }, $titles),
            function ($title) {
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
     * Create draft project(s) from a quote
     * PATH 1: Quote → Draft Projects
     */
    public function createDraftProjectFromQuote($quote)
    {
        Log::info('Creating draft project from quote', [
            'quote_id' => $quote->id
        ]);

        // Check if projects already exist for this quote
        $existingProjects = Project::where('quote_id', $quote->id)->get();

        if ($existingProjects->isNotEmpty()) {
            Log::warning("Draft project(s) already exist for quote, skipping creation.", [
                'quote_id' => $quote->id,
                'existing_project_ids' => $existingProjects->pluck('id')
            ]);
            return $existingProjects->count() === 1 ? $existingProjects->first() : $existingProjects->all();
        }

        return DB::transaction(function () use ($quote) {
            $hasProjectsValue = $quote->getAttribute('has_projects');

            if ($hasProjectsValue !== null && trim($hasProjectsValue) !== '') {
                $projectTitles = $this->extractValidProjectTitles($hasProjectsValue);

                if (empty($projectTitles)) {
                    Log::info('has_projects exists but has no valid titles → NO project created', [
                        'quote_id' => $quote->id,
                        'has_projects' => $hasProjectsValue
                    ]);
                    return null;
                }

                Log::info('Creating draft projects from quote', [
                    'quote_id' => $quote->id,
                    'titles' => $projectTitles
                ]);

                $projects = [];
                foreach ($projectTitles as $title) {
                    $project = $this->createSingleProject($quote, $title, 'draft');
                    if ($project) {
                        $projects[] = $project;
                    }
                }
                if(!empty($projects)){
                    $this->sendProjectsCreationEmail($projects, $quote);
                }
                return count($projects) === 1 ? $projects[0] : $projects;
            }

            Log::info('has_projects is null/empty → NO draft project created from quote', [
                'quote_id' => $quote->id
            ]);

            return null;
        });
    }

    /**
     * Create draft project(s) from an invoice (direct invoice path)
     * PATH 2: Invoice → Draft Projects
     */
    public function createDraftProjectFromInvoice($invoice)
    {
        Log::info('Creating draft project from invoice', [
            'invoice_id' => $invoice->id
        ]);

        // Check if projects already exist for this invoice
        $existingProjects = $invoice->projects;

        if ($existingProjects->isNotEmpty()) {
            Log::warning("Draft project(s) already exist for invoice, skipping creation.", [
                'invoice_id' => $invoice->id,
                'existing_project_ids' => $existingProjects->pluck('id')
            ]);
            return $existingProjects->count() === 1 ? $existingProjects->first() : $existingProjects->all();
        }

        return DB::transaction(function () use ($invoice) {
            $hasProjectsValue = $invoice->getAttribute('has_projects');

            if ($hasProjectsValue !== null && trim($hasProjectsValue) !== '') {
                $projectTitles = $this->extractValidProjectTitles($hasProjectsValue);

                if (empty($projectTitles)) {
                    Log::info('has_projects exists but has no valid titles → NO project created', [
                        'invoice_id' => $invoice->id,
                        'has_projects' => $hasProjectsValue
                    ]);
                    return null;
                }

                Log::info('Creating draft projects from invoice', [
                    'invoice_id' => $invoice->id,
                    'titles' => $projectTitles
                ]);

                $projects = [];
                foreach ($projectTitles as $title) {
                    $project = $this->createSingleProject($invoice, $title, 'draft');
                    if ($project) {
                        // Link project to invoice via pivot
                        $invoice->projects()->attach($project->id);
                        $projects[] = $project;
                    }
                }
                if(!empty($projects)){
                    $this->sendProjectsCreationEmail($projects, $invoice);
                }else{
                    $this->sendProjectsCreationEmail($projects, $invoice);
                }
                return count($projects) === 1 ? $projects[0] : $projects;
            }

            Log::info('has_projects is null/empty → NO draft project created from invoice', [
                'invoice_id' => $invoice->id
            ]);

            return null;
        });
    }

    /**
     * Update quote-based projects when quote is signed
     * Creates invoice and updates project status to pending
     */
    public function updateProjectsOnQuoteSigned($quote, $invoice)
    {
        Log::info('Updating projects after quote signed', [
            'quote_id' => $quote->id,
            'invoice_id' => $invoice->id
        ]);

        return DB::transaction(function () use ($quote, $invoice) {
            $projects = Project::where('quote_id', $quote->id)
                ->where('status', 'draft')
                ->get();

            if ($projects->isEmpty()) {
                Log::warning('No draft projects found for quote', [
                    'quote_id' => $quote->id
                ]);
                return null;
            }

            $updatedProjects = [];

            foreach ($projects as $project) {
                $project->update([
                    'status' => 'draft',
                    'description' => 'Project after quote acceptance.',
                ]);

                // Link project to invoice via pivot
                $invoice->projects()->attach($project->id);

                Log::info('Project updated after quote signed', [
                    'project_id' => $project->id,
                    'quote_id' => $quote->id,
                    'invoice_id' => $invoice->id
                ]);

                $updatedProjects[] = $project;
            }

            return count($updatedProjects) === 1 ? $updatedProjects[0] : $updatedProjects;
        });
    }

    /**
     * Update project status after payment
     * PATH 1: Quote → Invoice → Payment (projects already pending, just notify)
     * PATH 2: Invoice → Payment (draft → pending + activate)
     */
    public function updateProjectAfterPayment($invoice, array $paymentData = [])
    {
        Log::info('Updating project after payment', [
            'invoice_id' => $invoice->id,
            'payment_data' => $paymentData
        ]);

        return DB::transaction(function () use ($invoice) {
            // Get all projects linked to this invoice
            $projects = $invoice->projects;

            if ($projects->isEmpty()) {
                Log::warning('No projects found for invoice after payment', [
                    'invoice_id' => $invoice->id
                ]);
                return null;
            }

            $updatedProjects = [];

            foreach ($projects as $project) {
                // If project is still draft (direct invoice path), activate it
                if ($project->status === 'draft') {
                    $project->update([
                        'status' => 'pending',
                        'description' => 'Project activated after payment.',
                    ]);

                    Log::info('Draft project activated after payment', [
                        'project_id' => $project->id,
                        'invoice_id' => $invoice->id
                    ]);
                } else {
                    Log::info('Payment notification for pending project', [
                        'project_id' => $project->id,
                        'invoice_id' => $invoice->id
                    ]);
                }

                $updatedProjects[] = $project;
            }

            // Send ONE email for all projects
            if (!empty($updatedProjects)) {
                $this->sendProjectsCreationEmail($updatedProjects, $invoice);
            }

            return count($updatedProjects) === 1 ? $updatedProjects[0] : $updatedProjects;
        });
    }

    /**
     * Cancel project update - revert from pending back to draft
     */
    public function cancelProjectUpdate($invoice)
    {
        Log::info('Cancelling project update', [
            'invoice_id' => $invoice->id
        ]);

        return DB::transaction(function () use ($invoice) {
            $projects = $invoice->projects()->where('status', 'pending')->get();

            if ($projects->isEmpty()) {
                Log::warning('No pending projects found for invoice', [
                    'invoice_id' => $invoice->id
                ]);
                return false;
            }

            foreach ($projects as $project) {
                $project->update([
                    'status' => 'draft',
                    'description' => 'Payment cancelled - project reverted to draft.',
                ]);

                Log::info('Project reverted to draft', [
                    'project_id' => $project->id,
                    'invoice_id' => $invoice->id
                ]);
            }

            return true;
        });
    }

    /**
     * Delete project and all related data (tasks, assignments)
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

            // Delete project assignments
            $deletedAssignments = ProjectAssignment::where('project_id', $projectId)->delete();

            // Detach all invoice relationships
            $project->invoices()->detach();

            // Delete the project
            $project->delete();

            Log::info('Project deleted successfully', [
                'project_id' => $projectId,
                'tasks_deleted' => $deletedTasks,
                'assignments_deleted' => $deletedAssignments
            ]);

            return true;
        });
    }

    /**
     * Delete all projects associated with an invoice
     */
    public function deleteProjectsByInvoice($invoiceId)
    {
        Log::info('Deleting all projects for invoice', [
            'invoice_id' => $invoiceId
        ]);

        $invoice = \App\Models\Invoice::find($invoiceId);

        if (!$invoice) {
            Log::warning('Invoice not found', ['invoice_id' => $invoiceId]);
            return false;
        }

        $projects = $invoice->projects;

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
     * Create a single project with tasks
     */
    private function createSingleProject($source, $customTitle = null, $status = 'pending')
    {
        try {
            $today = now();
            $nextMonday = $today->isMonday() ? $today->copy() : $today->copy()->next('Monday');

            $sourceType = get_class($source);
            $isQuote = strpos($sourceType, 'Quote') !== false;
            $isInvoice = strpos($sourceType, 'Invoice') !== false;

            $startDate = $nextMonday->startOfDay();

            $totalServiceDays = $source->services->sum(function ($service) {
                return (float) ($service->time ?? 0);
            });

            // Ensure at least 1 day
            $totalServiceDays = max(1, $totalServiceDays);

            $endDate = $startDate->copy();
            $workdaysToAdd = ceil($totalServiceDays);

            // Add the required number of weekdays
            $addedDays = 0;
            while ($addedDays < $workdaysToAdd) {
                $endDate->addDay();
                
                if ($endDate->isWeekday()) {
                    $addedDays++;
                }
            }


            $projectName = $customTitle ?? ('Project for ' . ($isQuote ? 'Quote' : 'Invoice') . ' #' . $source->id);

            $projectData = [
                'client_id' => $source->client_id,
                'name' => $projectName,
                'description' => $status === 'draft'
                    ? 'Draft project created from ' . ($isQuote ? 'quote' : 'invoice') . '.'
                    : 'Auto-created project after payment.',
                'status' => $status,
                'start_date' => $nextMonday,
                'estimated_end_date' => $endDate,
            ];

            // Only set quote_id for quotes (invoices use pivot table)
            if ($isQuote) {
                $projectData['quote_id'] = $source->id;
            }

            $project = Project::create($projectData);

            Log::info('Successfully created project', [
                'project_id' => $project->id,
                'quote_id' => $project->quote_id ?? null,
                'client_id' => $source->client_id,
                'status' => $status,
                'name' => $projectName,
                'source_type' => $sourceType
            ]);

            // Log activity
            if (isset($this->activityLogger) && $source->client) {
                $this->activityLogger->log(
                    'clients_details',
                    'projects',
                    $source->client->id,
                    request()->ip(),
                    request()->userAgent(),
                    [
                        'project_id' => $project->id,
                        'project_name' => $projectName,
                        'client_id' => $source->client_id,
                        'status' => $status,
                        'source_type' => $isQuote ? 'quote' : 'invoice',
                        'source_id' => $source->id,
                        'url' => request()->fullUrl()
                    ],
                    "Project created from " . ($isQuote ? 'quote' : 'invoice') . " #{$source->id} for client #{$source->client->id}"
                );
            }

            // Create tasks
            $this->createTasksForProject($project, $source);

            // Assign team member
            $this->assignTeamToProject($project);

            // Note: Email is sent separately when multiple projects are created
            // to avoid sending multiple emails. See updateProjectAfterPayment method.

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
     * Create tasks for a project based on services
     */
    private function createTasksForProject($project, $source)
    {
        $source_services = $source->services;

        if ($source_services->isEmpty()) {
            Log::warning('No services found for source', [
                'source_id' => $source->id,
                'project_id' => $project->id
            ]);
            return;
        }

        // Calculate total tasks
        $totalTasks = 0;
        foreach ($source_services as $sourceService) {
            $serviceId = (string)($sourceService->service_id ?? $sourceService->id ?? null);

            if (!empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]) && is_array($this->defaultTasklist[$serviceId])) {
                $totalTasks += count($this->defaultTasklist[$serviceId]);
            } else {
                $totalTasks += 1;
            }
        }

        $taskPercentage = $totalTasks > 0 ? round(100 / $totalTasks, 2) : 0;
        $currentStart = $project->start_date->copy();

        foreach ($source_services as $sourceService) {
            $serviceId = (string)($sourceService->service_id ?? $sourceService->id ?? null);

            if (!empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]) && is_array($this->defaultTasklist[$serviceId])) {
                foreach ($this->defaultTasklist[$serviceId] as $taskData) {
                    $taskEnd = $currentStart->copy()->addDay();

                    Task::create([
                        'title' => $taskData['title'],
                        'project_id' => $project->id,
                        'description' => $taskData['description'],
                        'status' => 'pending',
                        'start_date' => $currentStart,
                        'end_date' => $taskEnd,
                        'percentage' => $taskPercentage,
                    ]);

                    $currentStart = $taskEnd;
                }
            } else {
                $taskEnd = $currentStart->copy()->addDay();

                Task::create([
                    'title' => $sourceService->service->name ?? 'Service Task',
                    'project_id' => $project->id,
                    'description' => $sourceService->service->description ?? 'Task for service',
                    'status' => 'pending',
                    'start_date' => $currentStart,
                    'end_date' => $taskEnd,
                    'percentage' => $taskPercentage,
                ]);

                $currentStart = $taskEnd;
            }
        }

        Log::info('Successfully created tasks for project', [
            'project_id' => $project->id,
            'total_tasks' => $totalTasks
        ]);
    }

    /**
     * Assign team member to project
     */
    private function assignTeamToProject($project)
    {
        $teamUser = TeamUser::withMax('assignments', 'created_at')
            ->orderBy('assignments_max_created_at')
            ->first();

        if ($teamUser) {
            ProjectAssignment::create([
                'project_id' => $project->id,
                'team_id' => $teamUser->id,
                'assigned_by' => 1,
                'assigned_at' => now(),
            ]);

            Log::info('Team member assigned to project', [
                'project_id' => $project->id,
                'team_id' => $teamUser->id
            ]);
        }
    }

    /**
     * Send project creation email for multiple projects (sends ONE email)
     */
    private function sendProjectsCreationEmail($projects, $source)
    {
        // Ensure projects is an array/collection
        if (!is_array($projects) && !($projects instanceof \Illuminate\Support\Collection)) {
            $projects = [$projects];
        }

        // Convert collection to array for easier handling
        $projectsArray = is_array($projects) ? $projects : $projects->all();
        $projectCount = count($projectsArray);

        Log::info('Sending project creation email for multiple projects...', [
            'project_count' => $projectCount,
            'project_ids' => array_map(function ($p) {
                return $p->id;
            }, $projectsArray),
        ]);

        $email = 'mangaka.wir@gmail.com';

        // Load relationships for all projects
        $projectsWithRelations = [];
        foreach ($projectsArray as $project) {
            // Ensure tasks are loaded
            if (!$project->relationLoaded('tasks')) {
                $project->load('tasks');
            }

            $assigned_team = ProjectAssignment::with('teamUser')
                ->where('project_id', $project->id)
                ->latest()
                ->first();

            $projectsWithRelations[] = [
                'project' => $project,
                'tasks' => $project->tasks,
                'assigned_team' => $assigned_team,
            ];
        }

        $data = [
            'projects' => $projectsWithRelations,
            'client' => $source->client,
            'client_id' => $source->client_id,
            'source' => $source,
            'project_count' => $projectCount,
        ];

        $firstProject = $projectsArray[0];
        $subject = $projectCount === 1
            ? 'New Project Created - #' . $firstProject->id
            : "New Projects Created - {$projectCount} Projects";

        if($source->client->user->preferences['email_notifications'] ?? true){
            try {
                Mail::to($email)->send(new ProjectCreatedMail([
                    'projects' => $projectsWithRelations,
                    'client' => $source->client,
                    'client_id' => $source->client_id,
                    'source' => $source,
                    'project_count' => $projectCount,
                    'subject' => $subject,
                ]));
                
                Log::info('Project creation email sent successfully', [
                    'email' => $email,
                    'project_count' => $projectCount,
                    'client_id' => $source->client_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send project creation email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'client_id' => $source->client_id,
                ]);
            }
        }
    }

}
