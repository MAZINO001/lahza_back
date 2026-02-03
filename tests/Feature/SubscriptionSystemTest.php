<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pack;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionCustomField;
use App\Models\SubscriptionCustomFieldValue;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $subscriptionService;
    protected Client $client;
    protected Pack $pack;
    protected Plan $plan;
    protected PlanPrice $monthlyPrice;
    protected PlanPrice $yearlyPrice;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriptionService = app(SubscriptionService::class);
        
        // Create test data
        $this->setupTestData();
    }

    protected function setupTestData()
    {
        // Create a user and client dynamically to avoid seed conflicts
        $user = \App\Models\User::create([
            'name' => 'Feature Test User',
            'email' => uniqid('feature_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->client = Client::create([
            'user_id' => $user->id,
            'client_type' => 'individual',
            'company' => 'Test Company',
            'phone' => '1234567890',
            'currency' => 'USD',
        ]);

        // Create a pack
        $this->pack = Pack::create([
            'name' => 'Business Pack',
            'description' => 'For growing businesses',
            'is_active' => true,
        ]);

        // Create a plan
        $this->plan = Plan::create([
            'pack_id' => $this->pack->id,
            'name' => 'Pro Plan',
            'description' => 'Professional features',
            'is_active' => true,
        ]);

        // Create prices
        $this->monthlyPrice = PlanPrice::create([
            'plan_id' => $this->plan->id,
            'interval' => 'monthly',
            'price' => 99.00,
            'currency' => 'USD',
        ]);

        $this->yearlyPrice = PlanPrice::create([
            'plan_id' => $this->plan->id,
            'interval' => 'yearly',
            'price' => 990.00,
            'currency' => 'USD',
        ]);

        // Create custom fields
        SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'max_projects',
            'label' => 'Maximum Projects',
            'type' => 'number',
            'default_value' => '10',
            'required' => true,
        ]);

        SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'max_users',
            'label' => 'Maximum Users',
            'type' => 'number',
            'default_value' => '5',
            'required' => true,
        ]);

        SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'has_api_access',
            'label' => 'API Access',
            'type' => 'boolean',
            'default_value' => 'false',
            'required' => false,
        ]);
    }

    
    public function test_it_can_create_a_pack()
    {
        $response = $this->postJson('/api/packs', [
            'name' => 'Enterprise Pack',
            'description' => 'For large organizations',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'pack' => ['id', 'name', 'description', 'is_active']
            ]);

        $this->assertDatabaseHas('packs', [
            'name' => 'Enterprise Pack',
        ]);
    }

    
    public function test_it_can_list_packs()
    {
        $response = $this->getJson('/api/packs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'is_active', 'plans']
                ]
            ]);
    }

    
    public function test_it_can_show_a_pack()
    {
        $response = $this->getJson("/api/packs/{$this->pack->id}");

        $response->assertStatus(200)
            ->assertJson([
                'pack' => [
                    'id' => $this->pack->id,
                    'name' => $this->pack->name,
                ]
        ]);
    }

    
    public function test_it_can_update_a_pack()
    {
        $response = $this->putJson("/api/packs/{$this->pack->id}", [
            'name' => 'Updated Pack Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('packs', [
            'id' => $this->pack->id,
            'name' => 'Updated Pack Name',
        ]);
    }

    
    public function test_it_can_delete_a_pack()
    {
        $pack = Pack::create(['name' => 'To Delete', 'is_active' => true]);

        $response = $this->deleteJson("/api/packs/{$pack->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('packs', ['id' => $pack->id]);
    }

    
    public function test_it_can_create_a_plan_with_prices_and_custom_fields()
    {
        $response = $this->postJson('/api/plans', [
            'pack_id' => $this->pack->id,
            'name' => 'Starter Plan',
            'description' => 'For small teams',
            'is_active' => true,
            'prices' => [
                [
                    'interval' => 'monthly',
                    'price' => 49.00,
                    'currency' => 'USD',
                ],
            ],
            'custom_fields' => [
                [
                    'key' => 'max_storage',
                    'label' => 'Storage Limit (GB)',
                    'type' => 'number',
                    'default_value' => '100',
                    'required' => true,
                ],
            ],
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('plans', [
            'name' => 'Starter Plan',
            'pack_id' => $this->pack->id,
        ]);

        $plan = Plan::where('name', 'Starter Plan')->first();
        
        $this->assertDatabaseHas('plan_prices', [
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'price' => 49.00,
        ]);

        $this->assertDatabaseHas('subscription_custom_fields', [
            'plan_id' => $plan->id,
            'key' => 'max_storage',
        ]);
    }

    
    public function test_it_can_create_a_subscription()
    {
        $response = $this->postJson('/api/subscriptions', [
            'client_id' => $this->client->id,
            'plan_id' => $this->plan->id,
            'plan_price_id' => $this->monthlyPrice->id,
            'status' => 'active',
            'custom_field_values' => [
                'max_projects' => 20,
                'max_users' => 10,
                'has_api_access' => true,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'subscription' => ['id', 'client_id', 'plan_id', 'status']
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'client_id' => $this->client->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        // Verify custom field values
        $subscription = Subscription::where('client_id', $this->client->id)->first();
        $this->assertEquals(20, $subscription->getCustomFieldValue('max_projects'));
        $this->assertEquals(10, $subscription->getCustomFieldValue('max_users'));
        $this->assertTrue($subscription->getCustomFieldValue('has_api_access'));
    }

    
    public function test_it_validates_subscription_creation_data()
    {
        $response = $this->postJson('/api/subscriptions', [
            'client_id' => 999, // Non-existent
            'plan_id' => $this->plan->id,
            'plan_price_id' => $this->monthlyPrice->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    
    public function test_it_prevents_mismatched_plan_and_price()
    {
        $anotherPlan = Plan::create([
            'pack_id' => $this->pack->id,
            'name' => 'Another Plan',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/subscriptions', [
            'client_id' => $this->client->id,
            'plan_id' => $anotherPlan->id,
            'plan_price_id' => $this->monthlyPrice->id, // Belongs to different plan
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Plan price does not belong to the selected plan']);
    }

    
    public function test_it_can_cancel_subscription_immediately()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/cancel", [
            'immediate' => true,
        ]);

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertNotNull($subscription->ends_at);
    }

    
    public function test_it_can_cancel_subscription_at_period_end()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/cancel", [
            'immediate' => false,
        ]);

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertEquals($subscription->next_billing_at, $subscription->ends_at);
    }

    
    public function test_it_can_renew_a_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $originalBillingDate = $subscription->next_billing_at;

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/renew");

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertTrue($subscription->next_billing_at->gt($originalBillingDate));
    }

    
    public function test_it_can_change_subscription_plan()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        // Create a new plan
        $newPlan = Plan::create([
            'pack_id' => $this->pack->id,
            'name' => 'Premium Plan',
            'is_active' => true,
        ]);

        $newPrice = PlanPrice::create([
            'plan_id' => $newPlan->id,
            'interval' => 'monthly',
            'price' => 199.00,
            'currency' => 'USD',
        ]);

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/change-plan", [
            'plan_id' => $newPlan->id,
            'plan_price_id' => $newPrice->id,
            'immediate' => true,
        ]);

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals($newPlan->id, $subscription->plan_id);
        $this->assertEquals($newPrice->id, $subscription->plan_price_id);
    }

    
    public function test_it_can_check_subscription_limits()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_projects' => 10]
        );

        // Not reached limit
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/check-limit", [
            'limit_key' => 'max_projects',
            'current_usage' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'limit_key' => 'max_projects',
                'limit_value' => 10,
                'current_usage' => 5,
                'has_reached_limit' => false,
                'remaining' => 5,
            ]);

        // Reached limit
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/check-limit", [
            'limit_key' => 'max_projects',
            'current_usage' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'has_reached_limit' => true,
                'remaining' => 0,
            ]);

        // Exceeded limit
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/check-limit", [
            'limit_key' => 'max_projects',
            'current_usage' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'has_reached_limit' => true,
                'remaining' => 0,
            ]);
    }

    
    public function test_it_can_get_subscription_statistics()
    {
        // Create multiple subscriptions with different statuses
        $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'active'
        );

        $user2 = \App\Models\User::create([
            'name' => 'Feature Stat User',
            'email' => uniqid('feature_stat_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $user2->id, 'client_type' => 'individual', 'currency' => 'USD']);

        $this->subscriptionService->createSubscription(
            $client2,
            $this->plan,
            $this->monthlyPrice,
            [],
            'trial'
        );

        $response = $this->getJson('/api/subscriptions/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total',
                    'active',
                    'trial',
                    'cancelled',
                    'past_due',
                    'expired',
                    'mrr',
                    'arr',
                ]
            ]);

        $stats = $response->json('stats');
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['trial']);
    }

    
    public function test_it_calculates_mrr_correctly()
    {
        // Create monthly subscription
        $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'active'
        );

        $user2 = \App\Models\User::create([
            'name' => 'Feature MRR User',
            'email' => uniqid('feature_mrr_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $user2->id, 'client_type' => 'individual', 'currency' => 'USD']);

        // Create yearly subscription
        $this->subscriptionService->createSubscription(
            $client2,
            $this->plan,
            $this->yearlyPrice,
            [],
            'active'
        );

        $response = $this->getJson('/api/subscriptions/stats');
        $stats = $response->json('stats');

        // MRR = 99 (monthly) + (990/12) (yearly) = 99 + 82.5 = 181.5
        $expectedMRR = 99.00 + (990.00 / 12);
        $this->assertEquals(round($expectedMRR, 2), $stats['mrr']);
        
        // ARR = MRR * 12
        $this->assertEquals(round($expectedMRR * 12, 2), $stats['arr']);
    }

    
    public function test_it_can_get_active_subscription_for_client()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_projects' => 15]
        );

        $response = $this->getJson("/api/clients/{$this->client->id}/subscription");

        $response->assertStatus(200)
            ->assertJson([
                'subscription' => [
                    'id' => $subscription->id,
                    'client_id' => $this->client->id,
                ],
                'custom_values' => [
                    'max_projects' => 15,
                ]
            ]);
    }

    
    public function test_it_returns_404_when_client_has_no_active_subscription()
    {
        $user = \App\Models\User::create([
            'name' => 'NoSub User',
            'email' => uniqid('nosub_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = Client::create(['user_id' => $user->id, 'client_type' => 'individual', 'currency' => 'USD']);

        $response = $this->getJson("/api/clients/{$client->id}/subscription");

        $response->assertStatus(404)
            ->assertJson(['message' => 'No active subscription found']);
    }

    
    public function test_it_updates_custom_field_values()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_projects' => 10]
        );

        $response = $this->putJson("/api/subscriptions/{$subscription->id}", [
            'custom_field_values' => [
                'max_projects' => 25,
                'max_users' => 15,
            ],
        ]);

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals(25, $subscription->getCustomFieldValue('max_projects'));
        $this->assertEquals(15, $subscription->getCustomFieldValue('max_users'));
    }

    
    public function test_it_handles_different_custom_field_types()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [
                'max_projects' => 10,        // number
                'has_api_access' => true,    // boolean
            ]
        );

        // Test number type
        $this->assertEquals(10, $subscription->getCustomFieldValue('max_projects'));
        $this->assertIsInt($subscription->getCustomFieldValue('max_projects'));

        // Test boolean type
        $this->assertTrue($subscription->getCustomFieldValue('has_api_access'));
        $this->assertIsBool($subscription->getCustomFieldValue('has_api_access'));
    }

    
    public function test_it_can_list_subscriptions_with_filters()
    {
        // Create subscriptions with different statuses
        $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'active'
        );

        $user2 = \App\Models\User::create([
            'name' => 'List Test User',
            'email' => uniqid('list_test_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $user2->id, 'client_type' => 'individual', 'currency' => 'USD']);

        $this->subscriptionService->createSubscription(
            $client2,
            $this->plan,
            $this->monthlyPrice,
            [],
            'trial'
        );

        // Filter by status
        $response = $this->getJson('/api/subscriptions?status=active');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Filter by client
        $response = $this->getJson("/api/subscriptions?client_id={$this->client->id}");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    
    public function test_it_syncs_custom_fields_when_changing_plans()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_projects' => 10]
        );

        // Create new plan with different custom fields
        $newPlan = Plan::create([
            'pack_id' => $this->pack->id,
            'name' => 'Enterprise Plan',
            'is_active' => true,
        ]);

        $newPrice = PlanPrice::create([
            'plan_id' => $newPlan->id,
            'interval' => 'monthly',
            'price' => 299.00,
            'currency' => 'USD',
        ]);

        // Different custom field
        SubscriptionCustomField::create([
            'plan_id' => $newPlan->id,
            'key' => 'max_storage',
            'type' => 'number',
            'default_value' => '1000',
        ]);

        // Change plan
        $this->subscriptionService->changePlan($subscription, $newPlan, $newPrice, true);

        $subscription->refresh();
        
        // Old custom field should be gone
        $this->assertNull($subscription->getCustomFieldValue('max_projects'));
        
        // New custom field should have default value
        $this->assertEquals(1000, $subscription->getCustomFieldValue('max_storage'));
    }

    
public function test_it_can_add_price_to_plan()
{
    $response = $this->postJson("/api/plans/{$this->plan->id}/prices", [
        'interval' => 'quarterly',
        'price' => 270.00,
        'currency' => 'USD',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('plan_prices', [
        'plan_id' => $this->plan->id,
        'interval' => 'quarterly',
        'price' => 270.00,
    ]);
}


    
    public function test_it_prevents_duplicate_interval_prices()
    {
        $response = $this->postJson("/api/plans/{$this->plan->id}/prices", [
            'interval' => 'monthly', // Already exists
            'price' => 120.00,
            'currency' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Price for this interval already exists']);
    }

    
    public function test_it_can_update_plan_price()
    {
        $response = $this->putJson("/api/plans/{$this->plan->id}/prices/{$this->monthlyPrice->id}", [
            'price' => 109.00,
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('plan_prices', [
            'id' => $this->monthlyPrice->id,
            'price' => 109.00,
        ]);
    }

    
    public function test_it_can_add_custom_field_to_plan()
    {
        $response = $this->postJson("/api/plans/{$this->plan->id}/custom-fields", [
            'key' => 'max_bandwidth',
            'label' => 'Bandwidth Limit (GB)',
            'type' => 'number',
            'default_value' => '500',
            'required' => true,
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('subscription_custom_fields', [
            'plan_id' => $this->plan->id,
            'key' => 'max_bandwidth',
        ]);
    }

    
    public function test_it_prevents_duplicate_custom_field_keys()
    {
        $response = $this->postJson("/api/plans/{$this->plan->id}/custom-fields", [
            'key' => 'max_projects', // Already exists
            'label' => 'Projects',
            'type' => 'number',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Custom field with this key already exists']);
    }

    
    public function test_it_can_update_custom_field()
    {
        $customField = $this->plan->customFields()->where('key', 'max_projects')->first();

        $response = $this->putJson("/api/plans/{$this->plan->id}/custom-fields/{$customField->id}", [
            'default_value' => '15',
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('subscription_custom_fields', [
            'id' => $customField->id,
            'default_value' => '15',
        ]);
    }

    
    public function test_it_can_delete_custom_field()
    {
        $customField = SubscriptionCustomField::create([
            'plan_id' => $this->plan->id,
            'key' => 'temp_field',
            'type' => 'text',
        ]);

        $response = $this->deleteJson("/api/plans/{$this->plan->id}/custom-fields/{$customField->id}");

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('subscription_custom_fields', [
            'id' => $customField->id,
        ]);
    }

    
    public function subscription_scope_methods_work()
    {
        // Create subscriptions with different statuses
        $active = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'active'
        );

        $user2 = \App\Models\User::create([
            'name' => 'Scope Test User 2',
            'email' => uniqid('scope_user2_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $user2->id, 'client_type' => 'individual', 'currency' => 'USD']);
        $trial = $this->subscriptionService->createSubscription($client2, $this->plan, $this->monthlyPrice, [], 'trial');

        $user3 = \App\Models\User::create([
            'name' => 'Scope Test User 3',
            'email' => uniqid('scope_user3_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client3 = Client::create(['user_id' => $user3->id, 'client_type' => 'individual', 'currency' => 'USD']);
        $cancelled = $this->subscriptionService->createSubscription($client3, $this->plan, $this->monthlyPrice, [], 'active');
        $cancelled->update(['status' => 'cancelled']);

        // Test scopes
        $this->assertEquals(1, Subscription::active()->count());
        $this->assertEquals(1, Subscription::trial()->count());
        $this->assertEquals(1, Subscription::cancelled()->count());
    }

    
    public function subscription_helper_methods_work()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            [],
            'active'
        );

        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->isCancelled());
        $this->assertFalse($subscription->isPastDue());
        $this->assertFalse($subscription->isExpired());

        // Change status
        $subscription->update(['status' => 'trial']);
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->isActive());
    }

    
    public function test_it_can_filter_active_packs_and_plans()
    {
        // Create inactive pack
        $inactivePack = Pack::create([
            'name' => 'Inactive Pack',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/packs/active');
        $response->assertStatus(200);
        
        $packs = $response->json('packs');
        $this->assertGreaterThan(0, count($packs));
        
        // Verify all returned packs are active
        foreach ($packs as $pack) {
            $this->assertTrue($pack['is_active']);
        }
    }

    
    public function next_billing_date_is_calculated_correctly()
    {
        // Monthly subscription
        $monthlySubscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $this->assertEquals(
            now()->addMonth()->format('Y-m-d'),
            $monthlySubscription->next_billing_at->format('Y-m-d')
        );

        // Yearly subscription
        $user2 = \App\Models\User::create([
            'name' => 'Billing User',
            'email' => uniqid('billing_user_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $client2 = Client::create(['user_id' => $user2->id, 'client_type' => 'individual', 'currency' => 'USD']);
        $yearlySubscription = $this->subscriptionService->createSubscription(
            $client2,
            $this->plan,
            $this->yearlyPrice
        );

        $this->assertEquals(
            now()->addYear()->format('Y-m-d'),
            $yearlySubscription->next_billing_at->format('Y-m-d')
        );
    }

    
    public function test_it_can_delete_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice
        );

        $response = $this->deleteJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    
    public function cascading_deletes_work_correctly()
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->client,
            $this->plan,
            $this->monthlyPrice,
            ['max_projects' => 10]
        );

        $customFieldValueId = $subscription->customFieldValues()->first()->id;

        // Delete subscription
        $subscription->delete();

        // Custom field values should be deleted
        $this->assertDatabaseMissing('subscription_custom_field_values', [
            'id' => $customFieldValueId
        ]);
    }
}
