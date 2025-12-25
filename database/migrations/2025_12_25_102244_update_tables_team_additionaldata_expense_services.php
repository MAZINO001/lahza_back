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
         Schema::table('services', function (Blueprint $table) {
            $table->string('time')->default('1');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('repeatedly', ['none', 'weekly', 'monthly', 'yearly'])->default('none');
        });

         Schema::table('team_additional_data', function (Blueprint $table) {
            $table->string('portfolio')->nullable();
            $table->string('github')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('cv')->nullable();
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
