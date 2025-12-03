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
        Schema::create('project_additional_data', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->text('host_acc')->nullable();
            $table->text('website_acc')->nullable();
            $table->text('social_media')->nullable();
            $table->text('media_files')->nullable();
            $table->text('specification_file')->nullable();
            $table->text('logo')->nullable();
            $table->text('other')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_additional_data');
    }
};
