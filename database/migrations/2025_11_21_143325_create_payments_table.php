<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('user_id')->nullable(); // optional: who created it

            // Stripe references
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();

            // Amount
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('usd');

            // Status
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            $table->timestamps();

            // Foreign key
            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
