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
        Schema::create('team_additional_data', function (Blueprint $table) {
            $table->id();
             $table->foreignId('team_user_id')
                ->constrained('team_users')
                ->cascadeOnDelete()
                ->unique(); // 1-to-1 enforced

            // Financial
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('iban')->nullable();

            // Contract
            $table->enum('contract_type', ['CDI', 'CDD', 'Freelance', 'Intern'])->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('contract_file')->nullable();

            // Emergency
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Professional
            $table->string('job_title')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->text('certifications')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_additional_data');
    }
}
;