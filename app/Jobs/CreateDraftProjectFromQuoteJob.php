<?php

namespace App\Jobs;

use App\Models\Quotes;
use App\Services\ProjectCreationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateDraftProjectFromQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Quotes $quote
    ) {}

    public function handle(ProjectCreationService $projectCreationService): void
    {
        try {
            $this->quote->refresh();
            $projectCreationService->createDraftProjectFromQuote($this->quote);
        } catch (\Exception $e) {
            Log::error('CreateDraftProjectFromQuoteJob failed', [
                'quote_id' => $this->quote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
