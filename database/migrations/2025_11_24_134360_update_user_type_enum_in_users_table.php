<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing rows to avoid enum constraint errors
        DB::statement("UPDATE users SET user_type = 'team' WHERE user_type = 'teams'");

        // Modify ENUM — FULL DEFINITION (required by MySQL)
        DB::statement("
            ALTER TABLE users 
            MODIFY user_type 
            ENUM('client', 'team', 'intern', 'other') 
            CHARACTER SET utf8mb4 
            COLLATE utf8mb4_unicode_ci 
            NOT NULL 
            DEFAULT 'client'
        ");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET user_type = 'teams' WHERE user_type = 'team'");

        DB::statement("
            ALTER TABLE users 
            MODIFY user_type 
            ENUM('client', 'teams', 'intern', 'other') 
            CHARACTER SET utf8mb4 
            COLLATE utf8mb4_unicode_ci 
            NOT NULL 
            DEFAULT 'client'
        ");
    }
};
