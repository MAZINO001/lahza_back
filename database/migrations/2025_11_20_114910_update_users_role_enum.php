<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, modify the column to be a string
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('client')->change();
        });

        // Update any invalid role values to 'client'
        DB::table('users')
            ->whereNotIn('role', ['admin', 'manager', 'member', 'client'])
            ->update(['role' => 'client']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to enum if needed
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'member', 'client'])->default('client')->change();
        });
    }
};