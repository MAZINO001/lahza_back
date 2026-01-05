<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CalendarSummaryService;
class GenerateDailyAiSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
   protected $signature = 'ai:run-daily-summary';

    protected $description = 'Generates AI calendar summaries for all team members';

    public function handle(CalendarSummaryService $service)
    {
        $this->info('Starting AI Summary Generation...');

        try {
            $service->Summary();
            $this->info('Daily summary generated successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to generate summary: ' . $e->getMessage());
        }
    }
}
