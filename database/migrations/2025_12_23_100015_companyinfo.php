<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();

            // Basic company info
            $table->string('company_name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('stamp_path')->nullable();

            // Contact info
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('phone2', 50)->nullable();
            $table->string('website')->nullable();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Moroccan legal identifiers
            $table->string('ma_ice')->nullable();      // ICE
            $table->string('ma_if')->nullable();       // Identifiant Fiscal
            $table->string('ma_cnss')->nullable();     // CNSS
            $table->string('ma_rc')->nullable();       // Registre de Commerce
            $table->string('ma_vat')->nullable();      // TVA Maroc

            // French legal identifiers (if applicable)
            $table->string('fr_siret')->nullable();
            $table->string('fr_vat')->nullable();

            // Moroccan bank info
            $table->string('bank_name');
            $table->string('rib', 24)->nullable();          // Moroccan RIB
            $table->string('account_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
