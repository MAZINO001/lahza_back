<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;
class Invoice extends Model
{
    use LogsActivity;
    protected $fillable = [
        'client_id',
        'quote_id',
        'invoice_date',
        'due_date',
        'status',
        'notes',
        'total_amount',
        'balance_due',
        'has_projects',
        'checksum'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quotes::class);
    }

    public function invoiceServices()
    {
        return $this->hasMany(InvoiceService::class, 'invoice_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'invoice_services', 'invoice_id', 'service_id')
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
  public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

}
