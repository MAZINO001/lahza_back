<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // E.g., User, Order, Report
            $table->unsignedBigInteger('entity_id'); // ID of that entity
            $table->enum('run_type', ['trigger', 'daily']); // 'trigger' or 'daily'
            $table->json('input_data')->nullable(); // optional, store what was sent to AI
            $table->json('output_data')->nullable(); // AI response
            $table->string('status')->default('pending'); // 'pending', 'success', 'failed'
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            // Optional index for faster queries
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
