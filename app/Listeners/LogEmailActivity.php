<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LogEmailActivity
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        $message = $event->message;
        $subject = $message->getSubject();
        $to = $this->formatAddresses($message->getTo());
        $from = $this->formatAddresses($message->getFrom());
        
        $action = $event instanceof \Illuminate\Mail\Events\MessageSending ? 'sending_email' : 'sent_email';
        
        // Get client ID from email headers if available
        $clientId = null;
        $headers = $message->getHeaders();
        if ($headers->has('X-Client-Id')) {
            $clientIdHeader = $headers->get('X-Client-Id');
            $clientId = method_exists($clientIdHeader, 'getBodyAsString') 
                ? $clientIdHeader->getBodyAsString()
                : (string) $clientIdHeader;
        }
        
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'user_role'   => Auth::check() ? Auth::user()->role ?? null : null,
            'action'      => $action,
            'table_name'  => 'emails',
            'record_id'   => $clientId,
            'old_values'  => null,
            'new_values'  => [
                'subject' => $subject,
                'to' => $to,
                'from' => $from,
            ],
            'changes'     => null,
            'ip_address'  => Request::ip(),
            'ip_country'  => cache()->remember("geoip.".Request::ip(), now()->addDays(7), function () {
                return rescue(fn() => 
                    \Illuminate\Support\Facades\Http::timeout(2)
                        ->get("https://api.ipwho.is/".Request::ip())
                        ->json('country_code', 'XX')
                , 'XX', false);
            }),
            'user_agent'  => Request::userAgent(),
            'device'      => $this->detectDevice(Request::userAgent()),
            'url'         => Request::fullUrl(),
        ]);
    }
    
    /**
     * Format email addresses for display
     */
  protected function formatAddresses($addresses)
{
    if (empty($addresses)) {
        return [];
    }
    
    $formatted = [];
    foreach ($addresses as $email => $address) {
        if ($address instanceof \Symfony\Component\Mime\Address) {
            $formatted[] = $address->toString();
        } else {
            $formatted[] = is_string($address) ? $address : $email;
        }
    }
    
    return $formatted;
}
    
    /**
     * Simple device detection from user agent.
     */
    protected function detectDevice($userAgent)
    {
        if (empty($userAgent)) {
            return 'CLI';
        }
        
        $userAgent = strtolower($userAgent);
        if (strpos($userAgent, 'mobile') !== false) return 'Mobile';
        if (strpos($userAgent, 'tablet') !== false) return 'Tablet';
        return 'Desktop';
    }
}
