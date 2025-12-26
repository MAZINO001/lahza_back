<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Intern;
use App\Models\Other;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user (no related model needed as per the controller)
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@lahza.com',
            'password' => Hash::make('admin@lahza.com'),
            'role' => 'admin',
            'user_type' => 'other', // 'admin' is not a valid user_type, using 'other' instead
            'remember_token' => Str::random(10),
        ]);

        // Create client from Morocco
        $moroccanClient = User::create([
            'name' => 'Moroccan Client',
            'email' => 'client.morocco@example.com',
            'password' => Hash::make('client.morocco@example.com'),
            'role' => 'client',
            'user_type' => 'client',
            'remember_token' => Str::random(10),
            'preferences' => [
                'language' => 'en',
                'dark_mode' => false,
                'email_notifications' => true,
                'browser_notifications' => true,
            ],
        ]);

        // Create client record for Moroccan client
        $latestClient = Client::latest('id')->first();
        $nextNumber = $latestClient ? $latestClient->id + 1 : 1;
        $clientNumber = 'Client-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        Client::create([
            'user_id' => $moroccanClient->id,
            'company' => 'Moroccan Company',
            'address' => '123 Moroccan Street, Casablanca',
            'phone' => '+212600000000',
            'city' => 'Casablanca',
            'country' => 'maroc',
            'client_type' => 'company',
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $moroccanClient->id, 'permission_id' => 2],
        ]);

        // Create client from France
        $frenchClient = User::create([
            'name' => 'French Client',
            'email' => 'client.france@example.com',
            'password' => Hash::make('client.france@example.com'),
            'role' => 'client',
            'user_type' => 'client',
            'remember_token' => Str::random(10),
             'preferences' => [
                'language' => 'en',
                'dark_mode' => false,
                'email_notifications' => true,
                'browser_notifications' => true,
            ],
        ]);

        // Create client record for French client
        $nextNumber++;
        $clientNumber = 'Client-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        Client::create([
            'user_id' => $frenchClient->id,
            'company' => 'French Company',
            'address' => '456 Paris Street, Paris',
            'phone' => '+33123456789',
            'city' => 'Paris',
            'country' => 'France',
            'client_type' => 'company',
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $frenchClient->id, 'permission_id' => 2],
        ]);

        // Create team member
        $teamMember = User::create([
            'name' => 'Team Member',
            'email' => 'team.member@lahza.com',
            'password' => Hash::make('team.member@lahza.com'),
            'role' => 'member', // Using 'member' role for team members
            'user_type' => 'team',
            'remember_token' => Str::random(10),
        ]);

        // Create team user record
        TeamUser::create([
            'user_id' => $teamMember->id,
            'department' => 'Development',
            'poste' => 'Senior Developer',
        ]);
        $teamMember = User::create([
            'name' => 'Team Member2',
            'email' => 'team.member2@lahza.com',
            'password' => Hash::make('team.member2@lahza.com'),
            'role' => 'member', // Using 'member' role for team members
            'user_type' => 'team',
            'remember_token' => Str::random(10),
        ]);

        // Create team user record
        TeamUser::create([
            'user_id' => $teamMember->id,
            'department' => 'Development2',
            'poste' => 'Senior Developer 2',
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $teamMember->id, 'permission_id' => 5],
            ['user_id' => $teamMember->id, 'permission_id' => 2],
        ]);

        // Create intern
        $intern = User::create([
            'name' => 'Intern User',
            'email' => 'intern@lahza.com',
            'password' => Hash::make('intern@lahza.com'),
            'role' => 'member', // Using 'member' role for interns
            'user_type' => 'intern',
            'remember_token' => Str::random(10),
        ]);

        // Create intern record
        $internRecord = Intern::create([
            'user_id' => $intern->id,
            'department' => 'Development',
            'cv'=> 'test.pdf',
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $intern->id, 'permission_id' => 2],
            ['user_id' => $intern->id, 'permission_id' => 5],
        ]);

        // Create other user type
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other.user@lahza.com',
            'password' => Hash::make('other.user@lahza.com'),
            'role' => 'member', // Using 'member' role for other users
            'user_type' => 'other',
            'remember_token' => Str::random(10),
        ]);

        // Create other user record
        Other::create([
            'user_id' => $otherUser->id,
            'description' => 'This is an other type of user with custom role',
            'tags' => ['custom', 'other'],
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $otherUser->id, 'permission_id' => 2],
            ['user_id' => $otherUser->id, 'permission_id' => 5],
        ]);
    }
}
