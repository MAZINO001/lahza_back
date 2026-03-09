<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLoggerService
{
    /**
     * Log an activity
     *
     * @param string $action
     * @param string|null $tableName
     * @param int|null $recordId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return ActivityLog
     */
    public function log(
        string $action,
        ?string $tableName = null,
        ?int $recordId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $properties = [],
        $newValue = null
    ): ActivityLog {
        $ipAddress = $ipAddress ?: Request::ip();
        $userAgent = $userAgent ?: Request::userAgent();
        
        $deviceType = $this->detectDevice($userAgent);
        
        $logData = [
            'user_id'     => Auth::id(),
            'user_role'   => Auth::check() ? Auth::user()->role ?? null : null,
            'action'      => $action,
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'device_type' => $deviceType,
        ];

        // Add additional properties if provided
        if (!empty($properties)) {
            $logData['properties'] = json_encode($properties);
        }
        
        // Add new values if provided
        if ($newValue !== null) {
            $logData['new_values'] = is_string($newValue) ? $newValue : json_encode($newValue);
        }

        return ActivityLog::create($logData);
    }

    /**
     * Log an activity with explicit user context (for use in queued jobs where Auth is not available).
     */
    public function logWithUser(
        ?int $userId,
        ?string $userRole,
        string $action,
        ?string $tableName = null,
        ?int $recordId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $properties = [],
        $newValue = null
    ): ActivityLog {
        $deviceType = $this->detectDevice($userAgent ?? '');

        $logData = [
            'user_id'     => $userId,
            'user_role'   => $userRole,
            'action'      => $action,
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'device'      => $deviceType,
        ];

        if (!empty($properties)) {
            $logData['properties'] = json_encode($properties);
        }
        if ($newValue !== null) {
            $logData['new_values'] = is_string($newValue) ? $newValue : json_encode($newValue);
        }

        return ActivityLog::create($logData);
    }

    /**
     * Detects the client device from the user-agent string
     *
     * @param string $userAgent
     * @return string
     */
    public function detectDevice(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);
        
        if (str_contains($userAgent, 'mobile')) {
            return 'Mobile';
        }
        
        if (str_contains($userAgent, 'tablet')) {
            return 'Tablet';
        }
        
        return 'Desktop';
    }
}
