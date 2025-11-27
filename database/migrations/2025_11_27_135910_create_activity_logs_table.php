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
         Schema::dropIfExists('activity_logs');

        // Create new table
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('user_role')->nullable();
            $table->string('action'); // created, updated, deleted
            $table->string('table_name');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changes')->nullable(); // beautiful diff
            $table->string('ip_address')->nullable();
            $table->string('ip_country', 2)->default('XX');
            $table->text('user_agent')->nullable();
            $table->string('device')->default('Desktop');
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'record_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
