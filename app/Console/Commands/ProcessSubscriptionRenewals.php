<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-renewals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscription renewals and mark expired subscriptions';

    protected SubscriptionService $subscriptionService;

    /**
     * Create a new command instance.
     */
    public function __construct(SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing subscription renewals...');

        // Process renewals for active subscriptions due for billing
        $dueSubscriptions = Subscription::where('status', 'active')
            ->where('next_billing_at', '<=', now())
            ->get();

        $renewedCount = 0;
        foreach ($dueSubscriptions as $subscription) {
            try {
                $this->subscriptionService->processRenewal($subscription);
                $renewedCount++;
                $this->line("Renewed subscription #{$subscription->id} for client #{$subscription->client_id}");
            } 
            catch (\Exception $e) {
                $this->error("Failed to renew subscription #{$subscription->id}: " . $e->getMessage());
            }
        }

        // Mark expired subscriptions
        $expiredCount = $this->subscriptionService->markExpiredSubscriptions();

        $this->info("Processed {$renewedCount} renewals and marked {$expiredCount} subscriptions as expired.");

        return Command::SUCCESS;
    }
}
