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
        Schema::create('project_progress', function (Blueprint $table) {
        $table->id();


        $table->foreignId('team_id')->constrained('team_users')->cascadeOnDelete();
        $table->foreignId('project_id')->constrained()->cascadeOnDelete();
        $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();

        $table->integer('accumlated_percentage')->default(0);


        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_progress');
    }
};
