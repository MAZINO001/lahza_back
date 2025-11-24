<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Quotes;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        //
    }


    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }

    public function updated(Quotes $quotes)
    {
        // Only run if one of the signature fields actually changed
        if (! $quotes->wasChanged(['admin_signature', 'client_signature'])) {
            return;
        }

        // Was it NOT fully signed before, but IS fully signed now?
        $wasFullySigned  = $this->wasFullySigned($quotes->getOriginal());
        $isFullySigned   = $quotes->is_fully_signed; // ← uses your accessor!

        if (! $wasFullySigned && $isFullySigned) {
            // THIS IS THE MOMENT IT BECAME FULLY SIGNED
            $this->handleFullySigned($quotes);
        }
    }

    private function wasFullySigned(array $original): bool
    {
        return !empty($original['admin_signature']) && !empty($original['client_signature']);
    }

    private function handleFullySigned(Quotes $quotes): void
    {
        return dd($quotes);

















        // 2. Send notification/email

        // \App\Notifications\InvoiceFullySigned::dispatch($invoice);

        // 3. Fire an event for queues, webhooks, etc.

        // event(new \App\Events\InvoiceFullySigned($invoice));

        

    }
}
