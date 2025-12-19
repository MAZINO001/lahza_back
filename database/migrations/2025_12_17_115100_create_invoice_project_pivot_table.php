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
        // Create the pivot table
        Schema::create('invoice_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Ensure each project can only be associated with an invoice once
            $table->unique(['invoice_id', 'project_id']);
        });

        // Remove the invoice_id from projects table if it exists
        if (Schema::hasColumn('projects', 'invoice_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the invoice_id to projects table
        if (!Schema::hasColumn('projects', 'invoice_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->after('client_id')->constrained('invoices')->cascadeOnDelete();
            });
        }

        // Drop the pivot table
        Schema::dropIfExists('invoice_project');
    }
};
