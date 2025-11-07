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
        Schema::table('quotes_services', function (Blueprint $table) {
            $table->decimal('tax', 8, 2)->nullable()->after('quantity');
            $table->decimal('individual_total', 10, 2)->nullable()->after('tax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes_services', function (Blueprint $table) {
            $table->dropColumn(['tax', 'individual_total']);
        });
    }
};
