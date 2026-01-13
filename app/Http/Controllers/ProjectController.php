<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use App\Services\ProjectCreationService;


class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $projectCreationService;

    public function __construct(ProjectCreationService $projectCreationService)
    {
        $this->projectCreationService = $projectCreationService;
    }
    public function index()
    {
        $this->authorize('viewAny', Project::class);
        $user = Auth::user();
        return Project::with('invoices')->when($user->role === 'client', function ($query) use ($user) {
            $query->where('client_id', $user->client->id);
        })->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'start_date' => 'required|date',
            'estimated_end_date' => 'required|date|after_or_equal:start_date',
            // 'quote_id' => 'nullable|exists:quotes,id',
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
        $this->authorize('view',$project);
        return $project;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
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
        $this->authorize('delete');
        // Detach all invoices before deleting the project
        $project->invoices()->detach();

        // Delete the project
        $project->delete();

        return response()->json(null, 204);
    }
    public function getProjectInvoices(){
        $this->authorize('create', Project::class);
        return Project::doesntHave('invoices')->get()->toArray();
    }


public function assignProjectToInvoice(Request $request)
{
    $this->authorize('create', Project::class);

    $invoiceId = $request->input('invoice_id');
    $projectId = $request->input('project_id');

    // Get invoice model (you need the model to call the relation)
    $invoice = Invoice::findOrFail($invoiceId);

    // This line checks if the row exists; if not, it creates it
    $invoice->projects()->syncWithoutDetaching([$projectId]);

    return response()->json([
        'success' => true,
        'message' => 'Invoice and project linked successfully.'
    ]);
}
public function assignServiceToproject(Request $request, ProjectCreationService $projectService)
{
$this->authorize('create', Project::class);

    $projectId = $request->input('project_id');
    $serviceId = $request->input('service_id');

    $project = Project::findOrFail($projectId);

    // Attach service if not already linked
    $project->services()->syncWithoutDetaching([$serviceId]);

    // Fetch newly attached service to pass to task creator
    $service = \App\Models\Service::find($serviceId);

    // Create task(s) for this service
    $projectService->createTasksForExistingProject($project, collect([$service]));

    return response()->json([
        'success' => true,
        'message' => 'Project and service linked successfully, tasks created.',
    ]);
}
public function completeProject( Project $project)
{

    try {
        $this->projectCreationService->toggleProjectCompletion($project);
        return back()->with('success', 'Project updated successfully!');
    } catch (\Exception $e) {
        // Check if our specific lock message is in the exception
        $isLocked = str_contains($e->getMessage(), 'PROJECT_LOCKED');

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], $isLocked ? 403 : 422); // 403 is "Forbidden"
    }
}

public function getProjectServices(Project $project)
{
    $this->authorize('view', $project);

    return response()->json($project->services()->get());
}

public function getALLProjectInvoices(Project $project)
{
    $this->authorize('view', $project);

    return response()->json($project->invoices()->get());
}

public function deleteProjectInvoices(Project $project, Invoice $invoice)
{
    $this->authorize('delete', $project);

    if (!$project->invoices()->where('invoices.id', $invoice->id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Invoice not found in this project'
        ], 404);
    }

    $project->invoices()->detach($invoice->id);

    return response()->json([
        'success' => true,
        'message' => 'Invoice removed from project successfully'
    ]);
}

public function deleteProjectServices(Project $project, Service $service)
{
    $this->authorize('delete', $project);

    if (!$project->services()->where('services.id', $service->id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Service not found in this project'
        ], 404);
    }

    $project->services()->detach($service->id);

    return response()->json([
        'success' => true,
        'message' => 'Service removed from project successfully'
    ]);
}

}

