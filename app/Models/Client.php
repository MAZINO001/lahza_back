<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use LogsActivity;
    protected $fillable = [
        'user_id',
        'name',
        'company',
        'address',
        'phone',
        'city',
        'country',
        'currency',
        'client_type',
        'siren',
        'vat',
        'ice',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function quotes()
    {
        return $this->hasMany(Quotes::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Quotes::class);
    }
      public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    public function subscriptions()
{
    return $this->hasMany(Subscription::class);
}

    /**
     * Get the active subscription for this client.
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->latest('started_at');
    }

    /**
     * Check if client has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Get subscription custom field value.
     */
    public function getSubscriptionLimit(string $key)
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return null;
        }
        
        return $subscription->getCustomFieldValue($key);
    }

    /**
     * Check if client has reached a subscription limit.
     */
    public function hasReachedSubscriptionLimit(string $limitKey, int $currentUsage): bool
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return false; // No active subscription means no limits
        }
        
        $limit = $subscription->getCustomFieldValue($limitKey);
        
        if ($limit === null) {
            return false; // No limit set
        }
        
        return $currentUsage >= $limit;
    }

}
