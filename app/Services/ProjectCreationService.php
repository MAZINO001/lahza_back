<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectCreationService
{
    /**
     * Create a project + related tables for an invoice
     */
    public function createProjectForInvoice($invoice)
    {
        Log::info('Starting project creation for invoice #' . $invoice->id);
        
        return DB::transaction(function () use ($invoice) {
            $paidCount = $invoice->payments()->where('status', 'paid')->count();

            if ($paidCount === 1) {
            try {
                Log::debug('Creating project with data:', [
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'name' => 'Project for Invoice #' . $invoice->id,
                    'statu' => 'pending',
                    'start_date' => now()->toDateTimeString(),
                    'estimated_end_date' => now()->addDays(30)->toDateTimeString()
                ]);

                $project = Project::create([
                    'invoice_id' => $invoice->id,
                    'client_id'  => $invoice->client_id,
                    'name'       => 'Project for Invoice #' . $invoice->id,
                    'description'=> 'Auto-created project after first payment.',
                    'statu'      => 'pending',
                    'start_date' => now(),
                    'estimated_end_date' => now()->addDays(30),
                ]);
                
                Log::info('Successfully created project', [
                    'project_id' => $project->id,
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id
                ]);
                
                return $project;
                
            } catch (\Exception $e) {
                Log::error('Failed to create project', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            }elseif($paidCount >= 2){
                Log::error('project already created for this invoice', [
                    'invoice_id' => $invoice->id,
                ]);
            }


            


        });
    }
}
