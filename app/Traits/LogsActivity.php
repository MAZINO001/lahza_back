<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            // Only log if something actually changed
            if ($model->getDirty()) {
                $model->logActivity('updated');
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });

        // If your model uses SoftDeletes
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class))) {
            static::restored(function ($model) {
                $model->logActivity('restored');
            });
        }
    }

    public function logActivity($action)
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'model_type'  => get_class($this),
            'model_id'    => $this->id,
            'action'      => $action,
            'old_values'  => $action === 'updated' || $action === 'deleted'
                                ? $this->getOriginal()
                                : null,
            'new_values'  => $this->getAttributes(),
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
