<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name'        => 'Branding & Visual Identity',
                'description' => 'Complete brand identity creation...',
                'base_price'  => 3000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/branding/600/400',
            ],
            [
                'name'        => 'Social Media Management',
                'description' => 'Monthly content planning...',
                'base_price'  => 1500,
                'tax_rate'    => 19.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/smm/600/400',
            ],
            [
                'name'        => 'Social Media Advertising',
                'description' => 'Paid ads strategy...',
                'base_price'  => 1000,
                'tax_rate'    => 0.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/sma/600/400',
            ],
            [
                'name'        => 'Website Design & Development',
                'description' => 'Fully responsive website...',
                'base_price'  => 5000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/webdev/600/400',
            ],
            [
                'name'        => 'SEO Optimization',
                'description' => 'On-page optimization...',
                'base_price'  => 2000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/seo/600/400',
            ],
            [
                'name'        => 'Google Ads Management',
                'description' => 'Campaign setup...',
                'base_price'  => 1500,
                'tax_rate'    => 0.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/googleads/600/400',
            ],
            [
                'name'        => 'Content Creation',
                'description' => 'Professional photos, videos...',
                'base_price'  => 1000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/content/600/400',
            ],
            [
                'name'        => 'Email Marketing',
                'description' => 'Newsletter design...',
                'base_price'  => 600,
                'tax_rate'    => 19.00,
                'status'      => 'active',
                'image'       => 'https://picsum.photos/seed/email/600/400',
            ],
            [
                'name'        => 'E-Commerce Management',
                'description' => 'Product uploads...',
                'base_price'  => 1200,
                'tax_rate'    => 19.00,
                'status'      => 'inactive',
                'image'       => 'https://picsum.photos/seed/ecommerce/600/400',
            ],
            [
                'name'        => 'Copywriting',
                'description' => 'Web copy, ads copy...',
                'base_price'  => 500,
                'tax_rate'    => 19.00,
                'status'      => 'inactive',
                'image'       => 'https://picsum.photos/seed/copywriting/600/400',
            ],
            [
                'name'        => 'Legacy Flash Animation',
                'description' => 'Old-school Flash animations...',
                'base_price'  => 800,
                'tax_rate'    => 19.00,
                'status'      => 'inactive',
                'image'       => 'https://picsum.photos/seed/flash/600/400',
            ],
        ];


        foreach ($services as $service) {
            DB::table('services')->insert([
                'name'        => $service['name'],
                'description' => $service['description'],
                'base_price'  => $service['base_price'],
                'tax_rate'    => $service['tax_rate'],
                'status'      => $service['status'],
                'image'       => $service['image'],
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
        }
    }
}
