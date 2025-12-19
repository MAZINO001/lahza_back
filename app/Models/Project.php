<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'client_id', 'name', 'description', 'status',
        'start_date', 'estimated_end_date', 'quote_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function assignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function progress()
    {
        return $this->hasOne(ProjectProgress::class);
    }

    public function additionalData()
    {
        return $this->hasOne(ProjectAdditionalData::class);
    }
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class,'invoice_project')->withTimestamps();
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
} 
