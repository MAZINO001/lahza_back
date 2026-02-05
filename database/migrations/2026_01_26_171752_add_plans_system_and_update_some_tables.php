<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
     Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Plans (individual offerings)
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Plan prices (monthly/yearly)
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->enum('interval', ['monthly', 'yearly', 'quarterly']);
            $table->decimal('price', 12, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
        });

        // Plan custom fields (limits/features)
        Schema::create('subscription_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('key'); // e.g., max_projects
            $table->string('label')->nullable();
            $table->enum('type', ['number','boolean','text','json'])->default('text');
            $table->string('default_value')->nullable();
            $table->boolean('required')->default(false);
            $table->timestamps();
        });

        // Subscriptions (client’s active / historical plan)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('plan_price_id')->constrained('plan_prices')->cascadeOnDelete();
            $table->enum('status', ['trial','active','past_due','cancelled','expired'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
        });

        // Subscription custom field values (actual values per client subscription)
        Schema::create('subscription_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('subscription_custom_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Add nullable subscription_id to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('client_id')->constrained('subscriptions')->nullOnDelete();
        });
        // Add rrules to events for recurring events
        Schema::table('events', function (Blueprint $table) {
                    $table->json('rrule')->nullable()->after('status');
        });
           Schema::create('invoice_subscriptions', function(Blueprint $table){
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            
            $table->decimal('price_snapshot', 12, 2);
            $table->enum('billing_cycle', ['monthly','yearly']);
            
            $table->timestamps();
        });
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_subscription_id')->nullable()->constrained('invoice_subscriptions')->nullOnDelete();
            $table->enum('allocatable_type', ['invoice','subscription']);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
     

        Schema::create('quote_subscriptions', function(Blueprint $table){
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            
            $table->decimal('price_snapshot', 12, 2);
            $table->enum('billing_cycle', ['monthly','yearly']);
            
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function(Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn('subscription_id');
        });

        Schema::dropIfExists('subscription_custom_field_values');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_custom_fields');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('packs');
    }
};
