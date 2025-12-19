<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAdditionalData extends Model
{
      protected $fillable = [
        'project_id', 'client_id',
        'host_acc', 'website_acc', 'social_media', 'media_files',
        'specification_file', 'logo', 'other'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
}
