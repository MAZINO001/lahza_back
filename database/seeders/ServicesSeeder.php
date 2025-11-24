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
                'description' => 'Complete brand identity creation including logo, color palette, typography, and brand guidelines.',
                'base_price'  => 3000,
                'tax_rate'    => 19.00,        // e.g. 19% VAT
                'status'      => 'active',
            ],
            [
                'name'        => 'Social Media Management',
                'description' => 'Monthly content planning, posting, engagement handling, and performance tracking.',
                'base_price'  => 1500,
                'tax_rate'    => 19.00,
                'status'      => 'active',
            ],
            [
                'name'        => 'Social Media Advertising',
                'description' => 'Paid ads strategy, campaign setup, targeting, optimization, and reporting.',
                'base_price'  => 1000,
                'tax_rate'    => 0.00,         // often no VAT on ad spend management
                'status'      => 'active',
            ],
            [
                'name'        => 'Website Design & Development',
                'description' => 'Fully responsive website with UX design, SEO structure, and CMS integration.',
                'base_price'  => 5000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
            ],
            [
                'name'        => 'SEO Optimization',
                'description' => 'On-page optimization, keyword research, technical SEO, and ranking strategy.',
                'base_price'  => 2000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
            ],
            [
                'name'        => 'Google Ads Management',
                'description' => 'Campaign setup, optimization, keyword targeting, and analytics reporting.',
                'base_price'  => 1500,
                'tax_rate'    => 0.00,
                'status'      => 'active',
            ],
            [
                'name'        => 'Content Creation',
                'description' => 'Professional photos, videos, and creative assets for brand communication.',
                'base_price'  => 1000,
                'tax_rate'    => 19.00,
                'status'      => 'active',
            ],
            [
                'name'        => 'Email Marketing',
                'description' => 'Newsletter design, automations, segmentation, and analytics.',
                'base_price'  => 600,
                'tax_rate'    => 19.00,
                'status'      => 'active',
            ],
            [
                'name'        => 'E-Commerce Management',
                'description' => 'Product uploads, catalog optimization, and conversion strategies.',
                'base_price'  => 1200,
                'tax_rate'    => 19.00,
                'status'      => 'inactive',
            ],
            [
                'name'        => 'Copywriting',
                'description' => 'Web copy, ads copy, and brand messaging optimized for conversions.',
                'base_price'  => 500,
                'tax_rate'    => 19.00,
                'status'      => 'inactive',
            ],
            [
                'name'        => 'Legacy Flash Animation',
                'description' => 'Old-school Flash animations (no longer offered)',
                'base_price'  => 800,
                'tax_rate'    => 19.00,
                'status'      => 'inactive',
            ],
        ];

        foreach ($services as $service) {
            DB::table('services')->insert([
                'name'        => $service['name'],
                'description' => $service['description'],
                'base_price'  => $service['base_price'],
                'tax_rate'    => $service['tax_rate'],
                'status'      => $service['status'] ?? 'active',
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
        }
    }
}
