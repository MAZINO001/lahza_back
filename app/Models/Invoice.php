<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'quote_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'notes',
        'total_amount',
        'balance_due',
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

    public static function generateInvoiceNumber()
{
    $latest = Invoice::orderBy('id', 'desc')->value('invoice_number');

    if (!$latest) {
        return "INV-001";
    }

    // Extract number part
    $number = (int) str_replace('INV-', '', $latest);

    $number++;

    return "INV-" . str_pad($number, 3, '0', STR_PAD_LEFT);
}
}
