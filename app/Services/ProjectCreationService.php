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
        
        // Debug: Log the loaded task list keys and types
        if (app()->environment('local')) {
            Log::debug('Loaded task list configuration', [
                'keys' => array_keys($this->defaultTasklist),
                'key_types' => array_map('gettype', array_keys($this->defaultTasklist)),
                'total_services' => count($this->defaultTasklist),
                'config_loaded' => $config !== null
            ]);
        }
    }

    /**
     * Create a project + related tables for an invoice
     * 
     * @param mixed $invoice
     * @return Project
     * @throws \Exception
     */
    public function createProjectForInvoice($invoice)
    {
        Log::info('Starting project creation for invoice #' . $invoice->id);
        
        return DB::transaction(function () use ($invoice) {
            $paidCount = $invoice->payments()->where('status', 'paid')->count();

            if ($paidCount === 1) {
                try {
                    $today = now();
                    $nextMonday = $today->isMonday()
                        ? $today->copy()
                        : $today->copy()->next('Monday');


                    $project = Project::create([
                        'invoice_id' => $invoice->id,
                        'client_id'  => $invoice->client_id,
                        'name'       => 'Project for Invoice #' . $invoice->id,
                        'description'=> 'Auto-created project after first payment.',
                        'statu'      => 'pending',
                        'start_date'  => $nextMonday,
                        'estimated_end_date' => $nextMonday->copy()->addDays(7),
                    ]);
                    
                    Log::info('Successfully created project', [
                        'project_id' => $project->id,
                        'invoice_id' => $invoice->id,
                        'client_id' => $invoice->client_id
                    ]);
                    
                    // Create tasks for the project
                    $invoice_services = $invoice->services;
                    
                    if ($invoice_services->isEmpty()) {
                        Log::warning('No services found for invoice', [
                            'invoice_id' => $invoice->id
                        ]);
                        return $project;
                    }

                    Log::debug('Invoice services loaded', [
                        'count' => $invoice_services->count(),
                        'services' => $invoice_services->toArray()
                    ]);

                    // Calculate total number of tasks across all services
                    $totalTasks = 0;
                    foreach ($invoice_services as $invoiceService) {
                        // Get service ID and ensure consistent type (convert to string to match config keys)
                        $serviceId = (string)($invoiceService->service_id ?? $invoiceService->id ?? null);
                        
                        // Debug: Log detailed service information
                        Log::debug('Processing invoice service', [
                            'invoice_service' => $invoiceService->toArray(),
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

                    // Create tasks
                    foreach ($invoice_services as $invoiceService) {
                        // Get service ID and ensure consistent type (convert to string to match config keys)
                        $serviceId = (string)($invoiceService->service_id ?? $invoiceService->id ?? null);
                        
                        Log::debug('Creating tasks for service', [
                            'service_id' => $serviceId,
                            'has_default_tasks' => !empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]),
                            'available_tasklist_keys' => is_array($this->defaultTasklist) ? array_keys($this->defaultTasklist) : []
                        ]);
                        
                        if (!empty($this->defaultTasklist) && isset($this->defaultTasklist[$serviceId]) && is_array($this->defaultTasklist[$serviceId])) {
                            // Create tasks from default task list for this service
                        $currentStart = $project->start_date->copy();
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
                            
                            Log::info('Created tasks from default list', [
                                'service_id' => $serviceId,
                                'task_count' => count($this->defaultTasklist[$serviceId])
                            ]);
                        } else {
                            // Create a single generic task if service not in default list
                                $taskEnd = $currentStart->copy()->addDay();

                            Task::create([
                                'title' => $invoiceService->service->name ?? 'Service Task',
                                'project_id' => $project->id,
                                'description' => $invoiceService->service->description ?? 'Task for service',
                                'status' => 'pending',
                                'start_date'  => $currentStart,
                                'end_date'    => $taskEnd,      
                                'percentage' => $taskPercentage,
                            ]);
                                $currentStart = $taskEnd;
                            
                        }
                    }

                    Log::info('Successfully created tasks for project', [
                        'project_id' => $project->id,
                        'total_tasks' => $totalTasks
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
                    // After creating tasks and assignment:
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
    'client'  => $invoice->client,
    'invoice' => $invoice,
    'tasks'   => $project->tasks,
    'assigned_team' => $assigned_team,
];

// Send email using your method
Mail::send('emails.project_created', $data, function ($message) use ($email, $project) {
    $message->to($email)
            ->subject('New Project Created - #' . $project->id);
});

Log::info('Project creation email sent successfully.');
                    return $project;
                    
                } catch (\Exception $e) {
                    Log::error('Failed to create project', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
                } elseif ($paidCount >= 2) {
                    Log::info('Project already created for this invoice', [
                        'invoice_id' => $invoice->id,
                    ]);
                    // Return the existing project if needed
                    return Project::where('invoice_id', $invoice->id)->first();
                }
                
                return null;
            });
        }
    }