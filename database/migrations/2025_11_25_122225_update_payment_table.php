<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop foreign key first if it exists
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails('payments');

            if ($doctrineTable->hasForeignKey('payments_user_id_foreign')) {
                $table->dropForeign('payments_user_id_foreign');
            }

            // Drop old user_id column
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropColumn('user_id');
            }

            // Add client_id if not exists
            if (!Schema::hasColumn('payments', 'client_id')) {
                $table->unsignedBigInteger('client_id')->after('quote_id');
                // Optional foreign key
                $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            }

            // Add total column if missing
            if (!Schema::hasColumn('payments', 'total')) {
                $table->decimal('total', 10, 2)->after('stripe_payment_intent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop client_id foreign key first
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails('payments');

            if ($doctrineTable->hasForeignKey('payments_client_id_foreign')) {
                $table->dropForeign('payments_client_id_foreign');
            }

            // Restore user_id
            if (!Schema::hasColumn('payments', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('quote_id');
            }

            // Drop client_id
            if (Schema::hasColumn('payments', 'client_id')) {
                $table->dropColumn('client_id');
            }

            // Drop total
            if (Schema::hasColumn('payments', 'total')) {
                $table->dropColumn('total');
            }
        });
    }
};
