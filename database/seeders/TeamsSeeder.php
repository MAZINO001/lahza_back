<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('team_users')->insert([
            [
                'name' => 'Default Team',
                'department' => 'General',
                'description' => 'This is the default team used for initial setup or testing.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Development',
                'department' => 'Tech',
                'description' => 'Handles all development-related tasks.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing',
                'department' => 'Sales & Promotion',
                'description' => 'Responsible for outreach and brand promotion.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
