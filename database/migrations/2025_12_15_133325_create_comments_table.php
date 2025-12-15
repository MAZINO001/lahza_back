<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation
            $table->morphs('commentable'); // creates commentable_id + commentable_type

            // Author
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Content
            $table->text('body');

            // Optional flag
            $table->boolean('is_internal')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
