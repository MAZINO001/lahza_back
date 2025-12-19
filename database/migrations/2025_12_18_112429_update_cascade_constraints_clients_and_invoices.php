<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * CLIENTS → USERS
         * Delete user when client is deleted
         */
        Schema::table('clients', function (Blueprint $table) {
            // drop old FK
            $table->dropForeign(['user_id']);

            // re-add with cascade
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        /**
         * INVOICES → PAYMENTS
         * Delete payments when invoice is deleted
         */
        Schema::table('payments', function (Blueprint $table) {
            // drop old FK
            $table->dropForeign(['invoice_id']);

            // re-add with cascade
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('invoices')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        /**
         * CLIENTS → USERS (remove cascade)
         */
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
        });

        /**
         * INVOICES → PAYMENTS (remove cascade)
         */
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);

            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('invoices')
                  ->onDelete('restrict');
        });
    }
};
