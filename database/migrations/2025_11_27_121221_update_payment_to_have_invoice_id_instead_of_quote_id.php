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
        Schema::table('payments', function (Blueprint $table) {
               $table->dropForeign(['quote_id']);
            
            // Then drop the quote_id column
            $table->dropColumn('quote_id');

            // Add invoice_id instead
            $table->foreignId('invoice_id')->nullable()->after('id')->constrained('invoices')->nullOnDelete();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('have_invoice_id_instead_of_quote_id', function (Blueprint $table) {
            //
        });
    }
};
