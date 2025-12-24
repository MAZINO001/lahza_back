<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeamAdditionalData extends Model
{
 


    use HasFactory;

    protected $fillable = [
        'team_user_id',
        'bank_name',
        'bank_account_number',
        'iban',
        'contract_type',
        'contract_start_date',
        'contract_end_date',
        'contract_file',
        'emergency_contact_name',
        'emergency_contact_phone',
        'job_title',
        'salary',
        'certifications',
        'notes',
    ];

    public function teamUser()
    {
        return $this->belongsTo(TeamUser::class);
    }


}
