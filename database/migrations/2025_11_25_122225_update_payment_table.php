<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop foreign key if it exists
            $table->dropForeign(['user_id']); // Laravel will find it by column name

            // Rename user_id to client_id
            $table->renameColumn('user_id', 'client_id');

            // Add total column if missing
            if (!Schema::hasColumn('payments', 'total')) {
                $table->decimal('total', 10, 2)->after('stripe_payment_intent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop total column
            if (Schema::hasColumn('payments', 'total')) {
                $table->dropColumn('total');
            }

            // Rename client_id back to user_id
            $table->renameColumn('client_id', 'user_id');

            // Optional: re-add foreign key if needed
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
