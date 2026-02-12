<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionCustomField;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a new subscription for a client.
     */
    public function createSubscription(Client $client, Plan $plan, PlanPrice $planPrice, array $customFieldValues = [], string $status = 'active'): Subscription
    {
        return DB::transaction(function () use ($client, $plan, $planPrice, $customFieldValues, $status) {
            // Calculate dates based on billing interval
            $startedAt = now();
            $nextBillingAt = $this->calculateNextBillingDate($startedAt, $planPrice->interval);

            // Create the subscription
            $subscription = Subscription::create([
                'client_id' => $client->id,
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'status' => $status,
                'started_at' => $startedAt,
                'next_billing_at' => $nextBillingAt,
            ]);

            // Set custom field values
            if (!empty($customFieldValues)) {
                $this->setCustomFieldValues($subscription, $customFieldValues);
            }

            return $subscription->load(['plan', 'planPrice', 'customFieldValues.customField']);
        });
    }

    /**
     * Update subscription status.
     */
    public function updateStatus(Subscription $subscription, string $status): Subscription
    {
        $subscription->update(['status' => $status]);

        if ($status === 'cancelled') {
            $subscription->update(['cancelled_at' => now()]);
        }

        return $subscription->fresh();
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediate = false): Subscription
    {
        if ($immediate) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => now(),
            ]);
        } else {
            // Cancel at end of billing period
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => $subscription->next_billing_at,
            ]);
        }

        return $subscription->fresh();
    }

    /**
     * Renew a subscription.
     */
    public function renewSubscription(Subscription $subscription): Subscription
    {
        $planPrice = $subscription->planPrice;
        $nextBillingAt = $this->calculateNextBillingDate($subscription->next_billing_at, $planPrice->interval);

        $subscription->update([
            'status' => 'active',
            'next_billing_at' => $nextBillingAt,
        ]);

        return $subscription->fresh();
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, PlanPrice $newPlanPrice, bool $immediate = true): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan, $newPlanPrice, $immediate) {
            if ($immediate) {
                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'plan_price_id' => $newPlanPrice->id,
                    'next_billing_at' => $this->calculateNextBillingDate(now(), $newPlanPrice->interval),
                ]);

                // Update custom field values for new plan
                $this->syncCustomFieldsForNewPlan($subscription, $newPlan);
            } else {
                // Schedule plan change for next billing
                // You might want to store this in a separate field or queue
                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'plan_price_id' => $newPlanPrice->id,
                ]);
            }

            return $subscription->fresh(['plan', 'planPrice', 'customFieldValues.customField']);
        });
    }

    /**
     * Set custom field values for a subscription.
     */
    public function setCustomFieldValues(Subscription $subscription, array $values): void
    {
        // Expected formats (ID-based only):
        // 1) Array of objects: [
        //      ['custom_field_id' => 1, 'value' => 10],
        //      ['custom_field_id' => 2, 'value' => 5],
        //    ]
        // 2) Associative array keyed by custom_field_id:
        //    [1 => 10, 2 => 5]

        if (empty($values)) {
            return;
        }

        // Normalize associative "id => value" format into the object format
        $isListOfObjects = isset($values[0]) && is_array($values[0]) && array_key_exists('custom_field_id', $values[0]);

        if (!$isListOfObjects) {
            $normalized = [];
            foreach ($values as $fieldId => $value) {
                $normalized[] = [
                    'custom_field_id' => $fieldId,
                    'value' => $value,
                ];
            }
            $values = $normalized;
        }

        foreach ($values as $item) {
            if (!is_array($item) || !isset($item['custom_field_id'])) {
                continue;
            }

            $fieldId = $item['custom_field_id'];

            $customField = SubscriptionCustomField::where('plan_id', $subscription->plan_id)
                ->where('id', $fieldId)
                ->first();

            if (!$customField) {
                continue;
            }

            $rawValue = $item['value'] ?? null;

            // If value is explicitly null, we treat this as a delete request
            if ($rawValue === null) {
                $subscription->customFieldValues()
                    ->where('custom_field_id', $customField->id)
                    ->delete();
                continue;
            }

            $preparedValue = $customField->prepareValue($rawValue);

            $subscription->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $customField->id],
                ['value' => $preparedValue]
            );
        }
    }

    /**
     * Get custom field values as array.
     */
    public function getCustomFieldValues(Subscription $subscription): array
    {
        $values = [];

        foreach ($subscription->customFieldValues as $fieldValue) {
            $customField = $fieldValue->customField;
            $values[$customField->key] = $customField->castValue($fieldValue->value);
        }

        return $values;
    }

    /**
     * Check if subscription has reached a limit.
     */
    public function hasReachedLimit(Subscription $subscription, string $limitKey, int $currentUsage): bool
    {
        $limit = $subscription->getCustomFieldValue($limitKey);

        if ($limit === null) {
            return false; // No limit set
        }

        return $currentUsage >= $limit;
    }

    /**
     * Process subscription renewal (typically called by a scheduled job).
     */
    public function processRenewal(Subscription $subscription): void
    {
        // This would integrate with your payment system (Stripe, etc.)
        // For now, we'll just update the billing date
        
        if ($subscription->status === 'active' && $subscription->next_billing_at <= now()) {
            $this->renewSubscription($subscription);
        }
    }

    /**
     * Mark subscriptions as expired if past their end date.
     */
    public function markExpiredSubscriptions(): int
    {
        return Subscription::where('status', 'cancelled')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Calculate next billing date based on interval.
     */
    protected function calculateNextBillingDate(Carbon $startDate, string $interval): Carbon
    {
        return match ($interval) {
            'monthly' => $startDate->copy()->addMonth(),
            'yearly' => $startDate->copy()->addYear(),
            'quarterly' => $startDate->copy()->addMonths(3),
            default => $startDate->copy()->addMonth(),
        };
    }

    /**
     * Sync custom fields when changing to a new plan.
     */
    protected function syncCustomFieldsForNewPlan(Subscription $subscription, Plan $newPlan): void
    {
        $newPlanFields = $newPlan->customFields()->pluck('id');
        
        // Remove custom field values that don't belong to the new plan
        $subscription->customFieldValues()
            ->whereNotIn('custom_field_id', $newPlanFields)
            ->delete();

        // Add default values for new plan fields that don't have values yet
        $existingFieldIds = $subscription->customFieldValues()->pluck('custom_field_id');
        
        $newPlan->customFields()
            ->whereNotIn('id', $existingFieldIds)
            ->each(function ($field) use ($subscription) {
                if ($field->default_value !== null) {
                    $subscription->customFieldValues()->create([
                        'custom_field_id' => $field->id,
                        'value' => $field->prepareValue($field->default_value),
                    ]);
                }
            });
    }

    /**
     * Get active subscription for a client.
     */
public function getActiveSubscription(Client $client): ?Subscription
{
    return Subscription::where('client_id', $client->id)
        ->whereIn('status', ['active', 'trial'])

        
        ->where(function ($q) {
            $q->whereNull('ends_at')
              ->orWhere('ends_at', '>', now());
        })
        ->orderByRaw("
            CASE status
                WHEN 'active' THEN 2
                WHEN 'trial' THEN 1
                ELSE 0
            END DESC
        ")
        ->orderByDesc('started_at')
        ->first();
}


    /**
     * Get subscription statistics.
     */
    public function getSubscriptionStats(): array
    {
        return [
            'total' => Subscription::count(),
            'active' => Subscription::active()->count(),
            'trial' => Subscription::trial()->count(),
            'cancelled' => Subscription::cancelled()->count(),
            'past_due' => Subscription::pastDue()->count(),
            'expired' => Subscription::expired()->count(),
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateARR(),
        ];
    }

    /**
     * Calculate Monthly Recurring Revenue.
     */
    protected function calculateMRR(): float
    {
        $monthlySubscriptions = Subscription::active()
            ->with('planPrice')
            ->get()
            ->sum(function ($subscription) {
                if ($subscription->planPrice->interval === 'monthly') {
                    return $subscription->planPrice->price;
                } elseif ($subscription->planPrice->interval === 'yearly') {
                    return $subscription->planPrice->price / 12;
                }
                return 0;
            });

        return round($monthlySubscriptions, 2);
    }

    /**
     * Calculate Annual Recurring Revenue.
     */
    protected function calculateARR(): float
    {
        return round($this->calculateMRR() * 12, 2);
    }
}
