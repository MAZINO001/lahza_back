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
        Schema::table('files', function (Blueprint $table) {
        $table->string('original_name')->nullable()->after('path');
        $table->bigInteger('size')->nullable()->after('original_name');
        $table->string('mime_type')->nullable()->after('size');
        $table->string('fileable_type')->nullable()->change();
        $table->unsignedBigInteger('fileable_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file', function (Blueprint $table) {
            //
        });
    }
};
