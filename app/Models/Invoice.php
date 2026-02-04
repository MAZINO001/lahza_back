<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'checksum',
        'has_projects',
        'description',
        'subscription_id',
    ];

    protected $casts = [
        'has_projects' => 'array',
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

    public function projects()
    {
        return $this->belongsToMany(Project::class,'invoice_project')->withTimestamps();
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
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if this invoice is for a subscription.
     */
    public function isSubscriptionInvoice(): bool
    {
        return !is_null($this->subscription_id);
    }

    /**
     * Scope a query to only include subscription invoices.
     */
    public function scopeSubscriptionInvoices($query)
    {
        return $query->whereNotNull('subscription_id');
    }

    /**
     * Scope a query to only include non-subscription invoices.
     */
    public function scopeNonSubscriptionInvoices($query)
    {
        return $query->whereNull('subscription_id');
    }

   public function invoiceSubscriptions(): HasMany
{
    return $this->hasMany(InvoiceSubscription::class);
}

/**
 * Check if this invoice has subscription plans.
 */
public function hasSubscriptions(): bool
{
    return $this->invoiceSubscriptions()->exists();
}

/**
 * Check if this invoice has regular services.
 */
public function hasServices(): bool
{
    return $this->invoiceServices()->exists();
}

/**
 * Check if this invoice is mixed (has both services and subscriptions).
 */
public function isMixedInvoice(): bool
{
    return $this->hasServices() && $this->hasSubscriptions();
}

/**
 * Check if this invoice is subscription-only.
 */
public function isSubscriptionOnly(): bool
{
    return $this->hasSubscriptions() && !$this->hasServices();
}

/**
 * Get total amount from subscriptions.
 */
public function getSubscriptionsTotal(): float
{
    return $this->invoiceSubscriptions()->sum('price_snapshot');
}

/**
 * Get total amount from services.
 */
public function getServicesTotal(): float
{
    return $this->invoiceServices()->sum('individual_total');
}

/**
 * Check if all subscription plans have been activated.
 */
public function allSubscriptionsActivated(): bool
{
    return $this->invoiceSubscriptions()
        ->whereNull('subscription_id')
        ->doesntExist();
}
}
