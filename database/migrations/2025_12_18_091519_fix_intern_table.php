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
  
    Schema::table('interns', function (Blueprint $table) {

        // Required field
        $table->string('department')->nullable(false)->change();
        $table->string('cv')->nullable(false)->change();

        // Nullable fields
        $table->string('linkedin')->nullable()->change();
        $table->string('github')->nullable()->change();
        $table->string('portfolio')->nullable()->change();
        $table->date('start_date')->nullable()->change();
        $table->date('end_date')->nullable()->change();
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
