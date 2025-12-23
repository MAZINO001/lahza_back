<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();

            $table->string('owner_type'); // user, company, vendor, etc.
            $table->unsignedBigInteger('owner_id');

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('source_type', ['file', 'url']);
            $table->string('file_path')->nullable();
            $table->string('url')->nullable();
            $table->string('preview_image')->nullable();

            $table->string('issued_by')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->enum('status', ['active', 'expired', 'pending', 'revoked'])
                  ->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
