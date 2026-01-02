<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class CompanyInfo extends Model
{
    use HasFactory;

    // Table name (if not default 'company_infos')
    protected $table = 'company_details';

    // Mass assignable fields
    protected $fillable = [
        'company_name',
        'tagline',
        'description',
        'email',
        'phone',
        'phone2',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'ma_ice',
        'ma_if',
        'ma_cnss',
        'ma_rc',
        'ma_vat',
        'fr_siret',
        'fr_vat',
        'bank_name',
        'rib',
        'account_name',
        'terms_and_conditions', 
    ];

    // If you want, you can add accessors for files to get default paths
    public function getFiles()
    {
        return [
            'logo' => asset('logo.png'),
            'logo_dark' => asset('logo-dark.png'),
            'signature' => asset('images/admin_signature.png'),
            'stamp' => asset('images/stamp.png'),
        ];
    }   
}