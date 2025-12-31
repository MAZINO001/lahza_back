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
        // Schema::table('expenses', function (Blueprint $table) {
        //     $table->enum('repeatedly', ['none','daily', 'weekly', 'monthly','yearly'])->default('none');
        // }); column already exist lol
        Schema::table('events', function (Blueprint $table) {
            $table->enum('urgency', ['low', 'medium', 'high','urgent'])->default('medium')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
