<?php

namespace App\Listeners;

use App\Services\ActivityLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LogEmailActivity
{
    protected $activityLogger;

    public function __construct(ActivityLoggerService $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        // Only log when the email has been successfully sent
        if (!$event instanceof \Illuminate\Mail\Events\MessageSent) {
            return;
        }

        $message = $event->message;
        $subject = $message->getSubject();
        $to = $this->formatAddresses($message->getTo());
        $from = $this->formatAddresses($message->getFrom());
        
        $action = 'clients_details';
        
        // Get client ID from email headers if available
        $clientId = null;
        $headers = $message->getHeaders();
        if ($headers->has('X-Client-Id')) {
            $clientIdHeader = $headers->get('X-Client-Id');
            $clientId = method_exists($clientIdHeader, 'getBodyAsString') 
                ? $clientIdHeader->getBodyAsString()
                : (string) $clientIdHeader;
        }

        $this->activityLogger->log(
            $action,
            'emails',
            $clientId,
            Request::ip(),
            Request::userAgent(),
            [
                'subject' => $subject,
                'to' => $to,
                'from' => $from,
                'user_id' => Auth::id(),
                'user_role' => Auth::check() ? Auth::user()->role ?? null : null,
                'url' => Request::fullUrl()
            ]
        );
    }

    /**
     * Format email addresses for display
     * 
     * @param array|null $addresses
     * @return array
     */
    protected function formatAddresses($addresses)
    {
        if (empty($addresses)) {
            return [];
        }

        $formatted = [];
        
        if (is_string($addresses)) {
            return [$addresses];
        }

        foreach ($addresses as $address => $name) {
            if (is_numeric($address)) {
                $formatted[] = $name;
            } else {
                $formatted[] = "$name <$address>";
            }
        }
        
        return $formatted;
    }
    
    /**
     * Simple device detection from user agent.
     */

}
