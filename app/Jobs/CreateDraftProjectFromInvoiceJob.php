<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\ProjectCreationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateDraftProjectFromInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function handle(ProjectCreationService $projectCreationService): void
    {
        try {
            $this->invoice->refresh();
            $projectCreationService->createDraftProjectFromInvoice($this->invoice);
        } catch (\Exception $e) {
            Log::error('CreateDraftProjectFromInvoiceJob failed', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
