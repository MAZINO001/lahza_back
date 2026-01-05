<?php

namespace App\Services;

use App\Models\Project;
use App\Models\AiRun;
use App\Models\Task;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectTaskService
{
    /**
     * Phase 1: Generate the AI Draft and save to AiRun
     */
    public function generateProjectDraft(Project $project)
    {
        try {
            // 1. Get Services from the Quote or Invoice
            // Fix: Get the first invoice or quote, then its services
            $invoice = $project->invoices()->first();
            
            if (!$invoice) {
                throw new \Exception('No invoice found for this project');
            }
            
            $services = $invoice->services ?? collect();
            
            if ($services->isEmpty()) {
                throw new \Exception('No services found in the invoice');
            }
            
            $totalAllocatedHours = $services->sum('time');

            // 2. Build Context for AI
            $context = "Project: {$project->name}\nDescription: {$project->description}\n";
            $context .= "Total Hours to distribute: {$totalAllocatedHours}\n";
            $context .= "Services provided in Quote:\n";
            
            foreach ($services as $s) {
                $context .= "- {$s->name}: {$s->time} hours\n";
            }

            // 3. Call Gemini
            $prompt = $this->getTaskPlannerPrompt($context);
            
            $response = Gemini::generativeModel(model: 'gemini-2.5-flash')
                ->generateContent($prompt);

            // Check if response has content
            if (!$response || !$response->text()) {
                throw new \Exception('Empty response from Gemini API');
            }

            // More robust JSON cleaning
            $rawText = $response->text();
            Log::info('Raw Gemini Response:', ['response' => $rawText]);
            
            // Remove markdown code blocks
            $cleanJson = preg_replace('/```json\s*|\s*```/', '', $rawText);
            $cleanJson = trim($cleanJson);
            
            $draftData = json_decode($cleanJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON Decode Error:', [
                    'error' => json_last_error_msg(),
                    'raw_text' => $rawText,
                    'cleaned_json' => $cleanJson
                ]);
                throw new \Exception('Failed to parse AI response: ' . json_last_error_msg());
            }

            // Validate the structure
            if (!is_array($draftData) || empty($draftData)) {
                throw new \Exception('Invalid task structure returned from AI');
            }

            // 4. Save to AiRun as a "Pending" draft
            return AiRun::create([
                'entity_type' => 'Project Tasks Update',
                'entity_id' => $project->id,
                'run_type' => 'trigger',
                'input_data' => ['prompt' => $context],
                'output_data' => $draftData,
                'status' => 'pending_review',
                'ran_at' => now(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Project Draft Generation Failed:', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Save failed run
            AiRun::create([
                'entity_type' => 'Project Tasks Update',
                'entity_id' => $project->id,
                'run_type' => 'trigger',
                'input_data' => ['error' => $e->getMessage()],
                'output_data' => null,
                'status' => 'failed',
                'ran_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Phase 2: Convert approved AiRun data into real Tasks
     */
    public function convertDraftToTasks($aiRunId, array $finalTasksData)
    {
        return DB::transaction(function () use ($aiRunId, $finalTasksData) {
            $aiRun = AiRun::findOrFail($aiRunId);
            
            foreach ($finalTasksData as $task) {
                Task::create([
                    'project_id' => $aiRun->entity_id,
                    'title' => $task['title'],
                    'description' => $task['description'] ?? '',
                    'estimated_hours' => $task['hours'] ?? 0,
                    // Add start/end dates logic here if needed
                ]);
            }

            $aiRun->update(['status' => 'completed']);
            return true;
        });
    }

    private function getTaskPlannerPrompt($context)
    {
        return <<<PROMPT
You are a Senior Technical Project Manager. Break down this project into tasks based ONLY on the provided services.

$context

STRICT RULES:
1. Use ONLY the service names provided above
2. EVERY task MUST have a "hours" field (number)
3. Total hours across ALL tasks MUST equal the total hours shown above
4. Tasks must be specific and technical
5. Return ONLY valid JSON - no markdown, no explanations

Required JSON format:
[
  {
    "title": "Specific Task Name",
    "description": "Detailed explanation",
    "hours": 4
  }
]

Respond with the JSON array only.
PROMPT;
    }
}