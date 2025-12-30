<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventGuest extends Model
{
    protected $fillable = ['event_id', 'event_id', 'user_id'];

    public $timestamps = true;

    /**
     * Get the event that owns this guest record.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the polymorphic guestable model (TeamUser, Client, Intern, or Other).
     */
    public function guestable()
    {
        return $this->morphTo();
    }
}

