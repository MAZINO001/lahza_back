<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
<<<<<<< HEAD
        $this->call(PermissionsSeeder::class);
        $this->call(ServicesSeeder::class);
        $this->call(OffersSeeder::class);
        $this->call(UserSeeder::class);
=======
        $this->call([
            PermissionsSeeder::class,
            ServicesSeeder::class,
            OffersSeeder::class,
            UserSeeder::class,
        ]);
>>>>>>> oussama
    }
}
