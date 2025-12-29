<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    // Fillable fields for mass assignment
    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'description',
        'start_hour',
        'end_hour',
        'category',
        'other_notes',
        'status',
        'url',
        'type',
        'repeatedly'
    ];

    /**
     * The teams that belong to the event.
     */
    public function teamUser()
    {
        return $this->belongsToMany(
            TeamUser::class,
            'event_team',
            'event_id',
            'team_id'
        );
    }

    /**
     * Get all guests (polymorphic) for the event (team, client, intern, or others).
     */
  public function guests()
    {
        return $this->belongsToMany(User::class, 'event_guests', 'event_id', 'user_id')
                    ->withTimestamps();
    }
}
