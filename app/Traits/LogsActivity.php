<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            if ($model->getDirty()) {
                $model->logActivity('updated');
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });

        // Handle SoftDeletes
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class))) {
            static::restored(function ($model) {
                $model->logActivity('restored');
            });
        }
    }

    public function logActivity($action)
    {
        $oldValues = $action === 'updated' || $action === 'deleted' 
            ? $this->getOriginal() 
            : null;

        $newValues = $this->getAttributes();

        // Calculate changes (diff)
        $changes = [];
        if ($oldValues && $newValues) {
            foreach ($newValues as $key => $value) {
                if (!array_key_exists($key, $oldValues) || $oldValues[$key] !== $value) {
                    $changes[$key] = [
                        'old' => $oldValues[$key] ?? null,
                        'new' => $value
                    ];
                }
            }
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'user_role'   => Auth::check() ? Auth::user()->role ?? null : null,
            'action'      => $action,
            'table_name'  => $this->getTable(),
            'record_id'   => $this->id,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'changes'     => $changes,
            'ip_address'  => request()->ip(),
            'ip_country' => cache()->remember("geoip.".request()->ip(), now()->addDays(7), function () {
                return rescue(fn() => 
                    Http::timeout(2)->get("https://api.ipwho.is/".request()->ip())->json('country_code', 'XX')
                , 'XX', false);
            }),            'user_agent'  => request()->userAgent(),
            
            'device'      => $this->detectDevice(request()->userAgent()),
            'url'         => request()->fullUrl(),
        ]);
    }

    /**
     * Simple device detection from user agent.
     */
    protected function detectDevice($userAgent)
    {
        $userAgent = strtolower($userAgent);
        if (strpos($userAgent, 'mobile') !== false) return 'Mobile';
        if (strpos($userAgent, 'tablet') !== false) return 'Tablet';
        return 'Desktop';
    }
}
