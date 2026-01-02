<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanyInfo;

class CompanyInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanyInfo::updateOrCreate(
            ['id' => 1], // ensures only one row
            [
                'company_name'   => 'LAHZA HM SARL AU',
                'tagline'        => 'Votre Partenaire de Croissance Digitale.',
                'description'    => "Depuis 10 ans, LAHZA est une agence de marketing digital franco-marocaine qui accompagne les entreprises dans leur croissance et leur transformation numérique. Basée à Tanger au Maroc, à Londres au UK et à Saint Denis en France, nous avons su nous imposer comme un acteur incontournable dans le secteur, en combinant créativité, stratégie et innovation pour répondre aux besoins des entreprises marocaines et françaises.",

                'email'          => 'contact@lahza.ma',
                'phone'          => '+212 627 340 875',
                'phone2'         => '0531068078',
                'website'        => 'https://www.lahza.ma',

                'address_line1'  => 'Rue Sayed Kotb, rés. Assedk,',
                'address_line2'  => 'Etg 1 B12',
                'city'           => 'Tanger',
                'state'          => 'Tanger - Tetouan - Al Hoceima',
                'country'        => 'Maroc',
                'postal_code'    => '90000',

                'ma_ice'         => '002056959000039',
                'ma_if'          => null,
                'ma_cnss'        => '5926350',
                'ma_rc'          => '88049',
                'ma_vat'         => null,

                'fr_siret'       => null,
                'fr_vat'         => null,

                'bank_name'      => 'AWB - Attijari Wafa Bank',
                'rib'            => '007640001433200000026029',
                'account_name'   => 'LAHZA HM',
            ]
        );
    }
}
