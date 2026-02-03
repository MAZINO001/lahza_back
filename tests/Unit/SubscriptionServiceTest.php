<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Pack;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionCustomField;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $service;
    protected Client $client;
    protected Plan $plan;
    protected PlanPrice $monthlyPrice;
    protected PlanPrice $yearlyPrice;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new SubscriptionService();
        // create a user dynamically to avoid conflicts with seeders
        $user = \App\Models\User::create([
            'name' => 'Test User',
            'email' => uniqid('test_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->client = Client::create([
            'user_id' => $user->id,
            'client_type' => 'individual',
            'currency' => 'USD',
        ]);

        $pack = Pack::create(['name' => 'Test Pack', 'is_active' => true]);
        
        $this->plan = Plan::create([
            'pack_id' => $pack->id,
            'name' => 'Test Plan',
            'is_active' => true,
        ]);

        $this->monthlyPrice = PlanPrice::create([
            'plan_id' => $this->plan->id,
            'interval' => 'monthly',
            'price' => 100.00,
            'currency' => 'USD',
        ]);

        $this->yearlyPrice = PlanPrice::create([
            'plan_id' => $this->plan->id,
            'interval' => 'yearly',
            'price' => 1000.00,
            'currency' => 'USD',
        ]);

        SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'max_items',
            'type' => 'number',
            'default_value' => '100',
        ]);
    }

    
    public function test_it_creates_subscription_with_correct_dates()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $this->assertNotNull($subscription->started_at);
        $this->assertNotNull($subscription->next_billing_at);
        $this->assertEquals('active', $subscription->status);
        
        // Next billing should be 1 month from now for monthly
        $expectedDate = now()->addMonth()->format('Y-m-d');
        $this->assertEquals($expectedDate, $subscription->next_billing_at->format('Y-m-d'));
    }

    
    public function test_it_creates_subscription_with_trial_status()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'trial'
        );

        $this->assertEquals('trial', $subscription->status);
    }

    
    public function test_it_sets_custom_field_values_on_creation()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_items' => 200]
        );

        $this->assertEquals(200, $subscription->getCustomFieldValue('max_items'));
    }

    
    public function test_it_updates_subscription_status()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $updatedSubscription = $this->service->updateStatus($subscription, 'past_due');

        $this->assertEquals('past_due', $updatedSubscription->status);
    }

    
    public function test_it_sets_cancelled_at_when_status_is_cancelled()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $this->assertNull($subscription->cancelled_at);

        $updatedSubscription = $this->service->updateStatus($subscription, 'cancelled');

        $this->assertEquals('cancelled', $updatedSubscription->status);
        $this->assertNotNull($updatedSubscription->cancelled_at);
    }

    
    public function test_it_cancels_subscription_immediately()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $cancelledSubscription = $this->service->cancelSubscription($subscription, immediate: true);

        $this->assertEquals('cancelled', $cancelledSubscription->status);
        $this->assertNotNull($cancelledSubscription->cancelled_at);
        $this->assertNotNull($cancelledSubscription->ends_at);
        $this->assertEquals(
            now()->format('Y-m-d H:i'),
            $cancelledSubscription->ends_at->format('Y-m-d H:i')
        );
    }

    
    public function test_it_cancels_subscription_at_period_end()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $nextBilling = $subscription->next_billing_at;

        $cancelledSubscription = $this->service->cancelSubscription($subscription, immediate: false);

        $this->assertEquals('cancelled', $cancelledSubscription->status);
        $this->assertNotNull($cancelledSubscription->cancelled_at);
        $this->assertEquals($nextBilling->format('Y-m-d H:i'), $cancelledSubscription->ends_at->format('Y-m-d H:i'));
    }

    
    public function test_it_renews_subscription()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $originalNextBilling = $subscription->next_billing_at;

        $renewedSubscription = $this->service->renewSubscription($subscription);

        $this->assertEquals('active', $renewedSubscription->status);
        $this->assertTrue($renewedSubscription->next_billing_at->gt($originalNextBilling));
        
        // Should be 1 month after the original next billing date
        $expectedDate = $originalNextBilling->copy()->addMonth();
        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $renewedSubscription->next_billing_at->format('Y-m-d')
        );
    }

    
    public function test_it_changes_plan_immediately()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $pack = Pack::create(['name' => 'New Pack', 'is_active' => true]);
        $newPlan = Plan::create([
            'pack_id' => $pack->id,
            'name' => 'New Plan',
            'is_active' => true,
        ]);

        $newPrice = PlanPrice::create([
            'plan_id' => $newPlan->id,
            'interval' => 'yearly',
            'price' => 2000.00,
            'currency' => 'USD',
        ]);

        $updatedSubscription = $this->service->changePlan($subscription, $newPlan, $newPrice, immediate: true);

        $this->assertEquals($newPlan->id, $updatedSubscription->plan_id);
        $this->assertEquals($newPrice->id, $updatedSubscription->plan_price_id);
        
        // Next billing should be recalculated for yearly
        $expectedDate = now()->addYear()->format('Y-m-d');
        $this->assertEquals($expectedDate, $updatedSubscription->next_billing_at->format('Y-m-d'));
    }

    
    public function test_it_syncs_custom_fields_when_changing_plans()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_items' => 150]
        );

        // New plan with different custom fields
        $pack = Pack::create(['name' => 'New Pack', 'is_active' => true]);
        $newPlan = Plan::create([
            'pack_id' => $pack->id,
            'name' => 'New Plan',
            'is_active' => true,
        ]);

        $newPrice = PlanPrice::create([
            'plan_id' => $newPlan->id,
            'interval' => 'monthly',
            'price' => 200.00,
            'currency' => 'USD',
        ]);

        SubscriptionCustomField::create([
            'plan_id' => $newPlan->id,
            'key' => 'max_storage',
            'type' => 'number',
            'default_value' => '500',
        ]);

        $updatedSubscription = $this->service->changePlan($subscription, $newPlan, $newPrice, immediate: true);

        // Old custom field should be removed
        $this->assertNull($updatedSubscription->getCustomFieldValue('max_items'));
        
        // New custom field should have default value
        $this->assertEquals(500, $updatedSubscription->getCustomFieldValue('max_storage'));
    }

    
    public function test_it_checks_if_limit_is_reached()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_items' => 100]
        );

        // Not reached
        $this->assertFalse($this->service->hasReachedLimit($subscription, 'max_items', 50));
        
        // Exactly at limit
        $this->assertTrue($this->service->hasReachedLimit($subscription, 'max_items', 100));
        
        // Over limit
        $this->assertTrue($this->service->hasReachedLimit($subscription, 'max_items', 150));
    }

    
    public function test_it_returns_false_when_checking_non_existent_limit()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $this->assertFalse($this->service->hasReachedLimit($subscription, 'non_existent', 100));
    }

    
    public function test_it_gets_custom_field_values_as_array()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_items' => 200]
        );

        $values = $this->service->getCustomFieldValues($subscription);

        $this->assertIsArray($values);
        $this->assertArrayHasKey('max_items', $values);
        $this->assertEquals(200, $values['max_items']);
    }

    
    public function test_it_sets_custom_field_values()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $this->service->setCustomFieldValues($subscription, [
            'max_items' => 300,
        ]);

        $subscription->refresh();
        $this->assertEquals(300, $subscription->getCustomFieldValue('max_items'));
    }

    
    public function test_it_updates_existing_custom_field_values()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_items' => 100]
        );

        $this->service->setCustomFieldValues($subscription, [
            'max_items' => 250,
        ]);

        $subscription->refresh();
        $this->assertEquals(250, $subscription->getCustomFieldValue('max_items'));
        
        // Should only have one custom field value record
        $this->assertEquals(1, $subscription->customFieldValues()->count());
    }

    
    public function test_it_gets_active_subscription_for_client()
    {
        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $activeSubscription = $this->service->getActiveSubscription($this->client);

        $this->assertNotNull($activeSubscription);
        $this->assertEquals($subscription->id, $activeSubscription->id);
    }

    
    public function test_it_returns_null_when_no_active_subscription()
    {
        $activeSubscription = $this->service->getActiveSubscription($this->client);

        $this->assertNull($activeSubscription);
    }

    
    public function test_it_prefers_active_over_trial_status()
    {
        // Create trial subscription
        $trialSubscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'trial'
        );

        // Create active subscription (more recent)
        sleep(1);
        $activeSubscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'active'
        );

        $result = $this->service->getActiveSubscription($this->client);

        $this->assertEquals($activeSubscription->id, $result->id);
    }

    
    public function test_it_calculates_subscription_stats()
    {
        // Create various subscriptions
        $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice, [], 'active');
        
        $user2 = \App\Models\User::create([
            'name' => 'Test User 2',
            'email' => uniqid('test_user2_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $user2->id, 'client_type' => 'individual', 'currency' => 'USD']);
        $this->service->createSubscription($client2, $this->plan, $this->monthlyPrice, [], 'trial');
        
        $user3 = \App\Models\User::create([
            'name' => 'Test User 3',
            'email' => uniqid('test_user3_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client3 = Client::create(['user_id' => $user3->id, 'client_type' => 'individual', 'currency' => 'USD']);
        $cancelledSub = $this->service->createSubscription($client3, $this->plan, $this->monthlyPrice, [], 'active');
        $this->service->updateStatus($cancelledSub, 'cancelled');

        $stats = $this->service->getSubscriptionStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['trial']);
        $this->assertEquals(1, $stats['cancelled']);
        $this->assertEquals(0, $stats['past_due']);
        $this->assertEquals(0, $stats['expired']);
    }

    
    public function test_it_calculates_mrr_for_monthly_subscriptions()
    {
        $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice, [], 'active');
        
        $userForMonthly = \App\Models\User::create([
            'name' => 'Monthly MRR User',
            'email' => uniqid('monthly_mrr_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $userForMonthly->id, 'client_type' => 'individual', 'currency' => 'USD']);
        $this->service->createSubscription($client2, $this->plan, $this->monthlyPrice, [], 'active');

        $stats = $this->service->getSubscriptionStats();

        $this->assertEquals(200.00, $stats['mrr']); // 2 * $100
    }

    
    public function test_it_calculates_mrr_for_yearly_subscriptions()
    {
        $this->service->createSubscription($this->client, $this->plan, $this->yearlyPrice, [], 'active');

        $stats = $this->service->getSubscriptionStats();

        $expectedMRR = 1000.00 / 12;
        $this->assertEquals(round($expectedMRR, 2), $stats['mrr']);
    }

    
    public function test_it_calculates_arr_correctly()
    {
        $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice, [], 'active');

        $stats = $this->service->getSubscriptionStats();

        $this->assertEquals(1200.00, $stats['arr']); // $100 * 12
    }

    
    public function test_it_marks_expired_subscriptions()
    {
        $subscription = $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice);
        
        // Cancel it and set end date to the past
        $subscription->update([
            'status' => 'cancelled',
            'ends_at' => now()->subDay(),
        ]);

        $markedCount = $this->service->markExpiredSubscriptions();

        $this->assertEquals(1, $markedCount);
        
        $subscription->refresh();
        $this->assertEquals('expired', $subscription->status);
    }

    
    public function test_it_does_not_mark_future_ended_subscriptions_as_expired()
    {
        $subscription = $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice);
        
        // Cancel it but end date is in the future
        $subscription->update([
            'status' => 'cancelled',
            'ends_at' => now()->addWeek(),
        ]);

        $markedCount = $this->service->markExpiredSubscriptions();

        $this->assertEquals(0, $markedCount);
        
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
    }

    
    public function test_it_processes_renewal_for_due_subscription()
    {
        $subscription = $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice);
        
        // Set next billing to now (due)
        $subscription->update(['next_billing_at' => now()]);
        
        $originalNextBilling = $subscription->next_billing_at;

        $this->service->processRenewal($subscription);

        $subscription->refresh();
        $this->assertTrue($subscription->next_billing_at->gt($originalNextBilling));
    }

    
    public function test_it_does_not_process_renewal_for_future_billing()
    {
        $subscription = $this->service->createSubscription($this->client, $this->plan, $this->monthlyPrice);
        
        $originalNextBilling = $subscription->next_billing_at;

        $this->service->processRenewal($subscription);

        $subscription->refresh();
        $this->assertEquals(
            $originalNextBilling->format('Y-m-d H:i'),
            $subscription->next_billing_at->format('Y-m-d H:i')
        );
    }

    
    public function test_it_handles_different_custom_field_types_correctly()
    {
        SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'is_premium',
            'type' => 'boolean',
            'default_value' => 'true',
        ]);

        SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'notes',
            'type' => 'text',
            'default_value' => 'Default note',
        ]);

        $subscription = $this->service->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [
                'max_items' => 250,
                'is_premium' => true,
                'notes' => 'Custom note',
            ]
        );

        // Number type
        $this->assertIsInt($subscription->getCustomFieldValue('max_items'));
        $this->assertEquals(250, $subscription->getCustomFieldValue('max_items'));

        // Boolean type
        $this->assertIsBool($subscription->getCustomFieldValue('is_premium'));
        $this->assertTrue($subscription->getCustomFieldValue('is_premium'));

        // Text type
        $this->assertIsString($subscription->getCustomFieldValue('notes'));
        $this->assertEquals('Custom note', $subscription->getCustomFieldValue('notes'));
    }
}
