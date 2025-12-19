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
        // Create events table
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // required
            $table->date('start_date'); // required
            $table->date('end_date');   // required
            $table->text('description')->nullable();
            $table->time('start_hour')->nullable();
            $table->time('end_hour')->nullable();
            $table->string('category')->nullable();
            $table->text('other_notes')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->nullable();
            $table->string('url')->nullable();
            $table->string('type')->nullable();
            $table->enum('repeatedly', ['none', 'daily', 'weekly', 'monthly', 'yearly'])->nullable();
            $table->timestamps();
        });

        // Create pivot table event_team
        Schema::create('event_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('team_users')->onDelete('cascade');

            $table->unique(['event_id', 'team_id']); // prevent duplicate assignments
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_team');
        Schema::dropIfExists('events');
    }
};
