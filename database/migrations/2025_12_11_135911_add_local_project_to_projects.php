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
        Schema::table('projects', function (Blueprint $table) {
            // 1️⃣ Make column nullable
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            // 2️⃣ Drop old FK if exists
            $table->dropForeign(['invoice_id']); 
            // 3️⃣ Add nullable FK
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            //
        });
    }
};
