<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class OffersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('offers')->insert([
            [
                'service_id' => 1,
                'title' => 'Black Friday Special',
                'description' => 'Get 20% off on this service during Black Friday week!',
                'discount_type' => 'percent',
                'discount_value' => 20.00,
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(5),
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'service_id' => 2,
                'title' => 'Holiday Fixed Discount',
                'description' => 'Flat $50 off for this service this holiday season.',
                'discount_type' => 'fixed',
                'discount_value' => 50.00,
                'start_date' => Carbon::now()->subDays(2),
                'end_date' => Carbon::now()->addDays(10),
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'service_id' => 3,
                'title' => 'Limited Time Offer',
                'description' => '15% discount for first 10 bookings this week.',
                'discount_type' => 'percent',
                'discount_value' => 15.00,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays(7),
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
