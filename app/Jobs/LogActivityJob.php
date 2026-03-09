<?php

namespace App\Jobs;

use App\Services\ActivityLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $userId,
        public ?string $userRole,
        public string $action,
        public ?string $tableName,
        public ?int $recordId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public array $properties = [],
        public $newValue = null
    ) {}

    public function handle(ActivityLoggerService $activityLogger): void
    {
        $activityLogger->logWithUser(
            $this->userId,
            $this->userRole,
            $this->action,
            $this->tableName,
            $this->recordId,
            $this->ipAddress,
            $this->userAgent,
            $this->properties,
            $this->newValue
        );
    }
}
