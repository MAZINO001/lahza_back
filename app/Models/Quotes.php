<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;
class Quotes extends Model
{
    use LogsActivity;
    protected $fillable = [
        'client_id',
        'quotation_date',
        'status',
        'notes',
        'quote_number',
        'total_amount',
    ];


    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'quotes_services', 'quote_id', 'service_id')
            ->withPivot(['quantity', 'tax', 'individual_total'])
            ->withTimestamps();
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function adminSignature()
    {
        return $this->files()->where('type', 'admin_signature')->first();
    }

    public function clientSignature()
    {
        return $this->files()->where('type', 'client_signature')->first();
    }

    public function quoteServices()
    {
        return $this->hasMany(Quotes_service::class, 'quote_id');
    }

    /**
     * Get the invoice associated with the quote.
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'quote_id');
    }
    // ro check if the quote is singed from both parties
    protected $appends = ['is_fully_signed'];

    public function getIsFullySignedAttribute()
    {
        return 
        // $this->adminSignature() &&
         $this->clientSignature();
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
