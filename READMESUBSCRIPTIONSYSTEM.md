# Subscription System Documentation

## Overview

This subscription system provides a complete solution for managing subscription-based services with support for:
- Multiple subscription packs and plans
- Flexible pricing (monthly/yearly)
- Custom fields for subscription limits and features
- Stripe integration support
- Subscription lifecycle management

## File Structure

```
Models/
├── Pack.php
├── Plan.php
├── PlanPrice.php
├── SubscriptionCustomField.php
├── Subscription.php
└── SubscriptionCustomFieldValue.php

Services/
└── SubscriptionService.php

Controllers/
├── PackController.php
├── PlanController.php
└── SubscriptionController.php

Console/Commands/
└── ProcessSubscriptionRenewals.php
```

## Installation

1. **Run the migration:**
```bash
php artisan migrate
```

2. **Add the routes to `routes/api.php`:**
Copy the routes from `subscription_routes.php` to your `routes/api.php` file.

3. **Update existing models:**
- Add the methods from `Client_model_additions.php` to your `App\Models\Client` class
- Add the methods from `Invoice_model_additions.php` to your `App\Models\Invoice` class

4. **Register the command in `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule)
{
    // Run daily at 1 AM
    $schedule->command('subscriptions:process-renewals')->dailyAt('01:00');
}
```

## Usage Examples

### 1. Creating a Pack with Plans

```php
use App\Models\Pack;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SubscriptionCustomField;

// Create a pack
$pack = Pack::create([
    'name' => 'Business Solutions',
    'description' => 'Complete business automation tools',
    'is_active' => true,
]);

// Create a plan
$plan = Plan::create([
    'pack_id' => $pack->id,
    'name' => 'Pro Plan',
    'description' => 'Perfect for growing businesses',
    'is_active' => true,
]);

// Add pricing
PlanPrice::create([
    'plan_id' => $plan->id,
    'interval' => 'monthly',
    'price' => 99.00,
    'currency' => 'USD',
]);

PlanPrice::create([
    'plan_id' => $plan->id,
    'interval' => 'yearly',
    'price' => 990.00, // 2 months free
    'currency' => 'USD',
]);

// Add custom fields (limits/features)
SubscriptionCustomField::create([
    'plan_id' => $plan->id,
    'key' => 'max_projects',
    'label' => 'Maximum Projects',
    'type' => 'number',
    'default_value' => '10',
    'required' => true,
]);

SubscriptionCustomField::create([
    'plan_id' => $plan->id,
    'key' => 'max_users',
    'label' => 'Maximum Users',
    'type' => 'number',
    'default_value' => '5',
    'required' => true,
]);

SubscriptionCustomField::create([
    'plan_id' => $plan->id,
    'key' => 'has_api_access',
    'label' => 'API Access',
    'type' => 'boolean',
    'default_value' => 'true',
    'required' => false,
]);
```

### 2. Creating a Subscription

```php
use App\Models\Client;
use App\Services\SubscriptionService;

$subscriptionService = app(SubscriptionService::class);

$client = Client::find(1);
$plan = Plan::find(1);
$planPrice = $plan->prices()->where('interval', 'monthly')->first();

// Create subscription with custom field values
$subscription = $subscriptionService->createSubscription(
    client: $client,
    plan: $plan,
    planPrice: $planPrice,
    customFieldValues: [
        'max_projects' => 20,  // Override default
        'max_users' => 10,     // Override default
        'has_api_access' => true,
    ],
    status: 'trial'  // or 'active'
);
```

### 3. Checking Subscription Limits

```php
// In your controller or service
$client = Client::find(1);
$subscription = $client->activeSubscription;

// Get the limit value
$maxProjects = $subscription->getCustomFieldValue('max_projects'); // Returns 20

// Check if limit is reached
$currentProjectCount = $client->projects()->count();
$hasReachedLimit = app(SubscriptionService::class)->hasReachedLimit(
    $subscription,
    'max_projects',
    $currentProjectCount
);

if ($hasReachedLimit) {
    return response()->json([
        'error' => 'You have reached your project limit. Please upgrade your plan.'
    ], 403);
}

// Or using the Client model method
if ($client->hasReachedSubscriptionLimit('max_projects', $currentProjectCount)) {
    return response()->json([
        'error' => 'You have reached your project limit.'
    ], 403);
}
```

### 4. Managing Subscription Lifecycle

```php
$subscriptionService = app(SubscriptionService::class);
$subscription = Subscription::find(1);

// Cancel subscription (at end of billing period)
$subscriptionService->cancelSubscription($subscription, immediate: false);

// Cancel subscription immediately
$subscriptionService->cancelSubscription($subscription, immediate: true);

// Renew subscription
$subscriptionService->renewSubscription($subscription);

// Change plan
$newPlan = Plan::find(2);
$newPlanPrice = $newPlan->prices()->where('interval', 'yearly')->first();
$subscriptionService->changePlan($subscription, $newPlan, $newPlanPrice, immediate: true);

// Update status
$subscriptionService->updateStatus($subscription, 'past_due');
```

### 5. API Endpoints Examples

#### Create a Pack
```bash
POST /api/packs
{
    "name": "Enterprise Solutions",
    "description": "Full-featured enterprise package",
    "is_active": true
}
```

#### Create a Plan with Prices and Custom Fields
```bash
POST /api/plans
{
    "pack_id": 1,
    "name": "Starter Plan",
    "description": "Perfect for small teams",
    "is_active": true,
    "prices": [
        {
            "interval": "monthly",
            "price": 49.00,
            "currency": "USD"
        },
        {
            "interval": "yearly",
            "price": 490.00,
            "currency": "USD"
        }
    ],
    "custom_fields": [
        {
            "key": "max_projects",
            "label": "Maximum Projects",
            "type": "number",
            "default_value": "5",
            "required": true
        },
        {
            "key": "max_users",
            "label": "Maximum Users",
            "type": "number",
            "default_value": "3",
            "required": true
        }
    ]
}
```

#### Create a Subscription
```bash
POST /api/subscriptions
{
    "client_id": 1,
    "plan_id": 1,
    "plan_price_id": 1,
    "status": "active",
    "custom_field_values": {
        "max_projects": 10,
        "max_users": 5
    }
}
```

#### Check Subscription Limit
```bash
POST /api/subscriptions/1/check-limit
{
    "limit_key": "max_projects",
    "current_usage": 8
}

Response:
{
    "limit_key": "max_projects",
    "limit_value": 10,
    "current_usage": 8,
    "has_reached_limit": false,
    "remaining": 2
}
```

#### Cancel Subscription
```bash
POST /api/subscriptions/1/cancel
{
    "immediate": false
}
```

#### Change Plan
```bash
POST /api/subscriptions/1/change-plan
{
    "plan_id": 2,
    "plan_price_id": 4,
    "immediate": true
}
```

#### Get Subscription Statistics
```bash
GET /api/subscriptions/stats

Response:
{
    "stats": {
        "total": 150,
        "active": 120,
        "trial": 15,
        "cancelled": 10,
        "past_due": 3,
        "expired": 2,
        "mrr": 12500.00,
        "arr": 150000.00
    }
}
```

### 6. Middleware Example for Subscription Checks

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionLimit
{
    public function handle(Request $request, Closure $next, string $limitKey)
    {
        $client = $request->user()->client;
        
        if (!$client) {
            return response()->json(['error' => 'No client associated with user'], 403);
        }

        $subscription = $client->activeSubscription;
        
        if (!$subscription) {
            return response()->json(['error' => 'No active subscription'], 403);
        }

        // Get current usage based on limit type
        $currentUsage = match($limitKey) {
            'max_projects' => $client->projects()->count(),
            'max_users' => $client->teamUsers()->count(),
            default => 0,
        };

        if ($client->hasReachedSubscriptionLimit($limitKey, $currentUsage)) {
            return response()->json([
                'error' => 'Subscription limit reached',
                'limit' => $limitKey,
                'upgrade_required' => true
            ], 403);
        }

        return $next($request);
    }
}

// Usage in routes
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware('check.subscription.limit:max_projects');
```

### 7. Querying Subscriptions

```php
// Get all active subscriptions
$activeSubscriptions = Subscription::active()
    ->with(['client', 'plan', 'planPrice'])
    ->get();

// Get subscriptions expiring soon
$expiringSoon = Subscription::where('status', 'cancelled')
    ->whereBetween('ends_at', [now(), now()->addDays(7)])
    ->get();

// Get client's subscription history
$client = Client::find(1);
$history = $client->subscriptions()
    ->with(['plan', 'planPrice'])
    ->orderBy('started_at', 'desc')
    ->get();

// Get subscriptions by plan
$plan = Plan::find(1);
$subscriptions = $plan->subscriptions()
    ->where('status', 'active')
    ->count();

// Get all custom field values for a subscription
$subscription = Subscription::find(1);
$customValues = app(SubscriptionService::class)->getCustomFieldValues($subscription);
// Returns: ['max_projects' => 10, 'max_users' => 5, 'has_api_access' => true]
```

### 8. Scheduled Tasks

The system includes a command to process subscription renewals:

```bash
php artisan subscriptions:process-renewals
```

This command should be scheduled in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('subscriptions:process-renewals')->dailyAt('01:00');
}
```

## Database Structure

### Packs
- Container for multiple related plans
- Can be activated/deactivated

### Plans
- Individual subscription offerings within a pack
- Can have multiple pricing options (monthly/yearly)
- Can define custom fields (limits/features)

### Plan Prices
- Defines pricing for different billing intervals
- Supports Stripe price IDs for integration

### Subscription Custom Fields
- Define limits and features for plans
- Support types: number, boolean, text, json
- Can have default values and required flags

### Subscriptions
- Active client subscriptions
- Track status, billing dates, cancellation
- Link to invoices
- Store custom field values

## Best Practices

1. **Always check subscription limits** before allowing resource creation
2. **Use the service layer** for subscription operations to ensure consistency
3. **Schedule the renewal command** to run daily
4. **Implement webhooks** for Stripe payment updates
5. **Set custom field default values** for better user experience
6. **Use database transactions** when creating/updating subscriptions
7. **Monitor MRR/ARR metrics** regularly using the stats endpoint

## Stripe Integration

To integrate with Stripe:

1. Store `stripe_price_id` when creating plan prices
2. Store `stripe_subscription_id` when creating subscriptions
3. Set up webhooks to handle:
   - Payment success → Renew subscription
   - Payment failed → Mark as past_due
   - Subscription cancelled → Update status
   - Customer updated → Sync data

## Error Handling

All controllers return consistent JSON responses:

```json
// Success
{
    "message": "Operation successful",
    "data": { ... }
}

// Validation Error
{
    "errors": {
        "field_name": ["Error message"]
    }
}

// Server Error
{
    "error": "Error message"
}
```

## Testing

Example test cases:

```php
public function test_can_create_subscription()
{
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    
    $response = $this->postJson('/api/subscriptions', [
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'plan_price_id' => $price->id,
    ]);
    
    $response->assertStatus(201);
    $this->assertDatabaseHas('subscriptions', [
        'client_id' => $client->id,
        'plan_id' => $plan->id,
    ]);
}

public function test_subscription_limit_check()
{
    $subscription = Subscription::factory()->create();
    
    // Create custom field
    $field = SubscriptionCustomField::factory()->create([
        'plan_id' => $subscription->plan_id,
        'key' => 'max_projects',
        'type' => 'number',
    ]);
    
    // Set value
    SubscriptionCustomFieldValue::factory()->create([
        'subscription_id' => $subscription->id,
        'custom_field_id' => $field->id,
        'value' => '5',
    ]);
    
    $response = $this->postJson("/api/subscriptions/{$subscription->id}/check-limit", [
        'limit_key' => 'max_projects',
        'current_usage' => 6,
    ]);
    
    $response->assertStatus(200);
    $response->assertJson(['has_reached_limit' => true]);
}
```
