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
                Schema::table('payments', function (Blueprint $table) {
                    $table->decimal('total', 10, 2)->default(0)->after('stripe_payment_intent_id')->change(); });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
