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
    Schema::table('users', function (Blueprint $table) {
        $table->enum('status', ['waiting_confirmation', 'confirmed', 'gold'])->default('waiting_confirmation');
        $table->string('otp_code')->nullable();
        $table->timestamp('otp_expires_at')->nullable();
        $table->timestamp('last_otp_verified_at')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'otp_code', 'otp_expires_at', 'last_otp_verified_at']);
        });
    }
};
