<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('tax_rate', 8, 2)->default(0.00)->after('base_price');
            $table->enum('status', ['active', 'inactive'])
                ->default('active')
                ->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'status']);
        });
    }
};
