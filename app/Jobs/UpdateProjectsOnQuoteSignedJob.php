<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Quotes;
use App\Services\ProjectCreationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateProjectsOnQuoteSignedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Quotes $quote,
        public Invoice $invoice
    ) {}

    public function handle(ProjectCreationService $projectCreationService): void
    {
        try {
            $this->quote->refresh();
            $this->invoice->refresh();
            $projectCreationService->updateProjectsOnQuoteSigned($this->quote, $this->invoice);
        } catch (\Exception $e) {
            Log::error('UpdateProjectsOnQuoteSignedJob failed', [
                'quote_id' => $this->quote->id,
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
