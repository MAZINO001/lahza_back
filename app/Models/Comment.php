<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
        'body',
        'user_id',
        'is_internal',
    ];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function clients()
    {
        return $this->belongsTo(Client::class);
    }
    public function services()
    {
        return $this->belongsTo(Service::class);
    }
    public function offers()
    {
        return $this->belongsTo(Offer::class);
    }
    public function intern(){
        return $this->belongsTo(Intern::class);
    }
    public function other(){
        return $this->belongsTo(Other::class);
    }
    public function team(){
        return $this->belongsTo(TeamUser::class);
    }
   
}
