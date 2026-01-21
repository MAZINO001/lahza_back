<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // 1. Get the starting point
            $lastVerified = $user->last_otp_verified_at ?? $user->created_at;
            
            // 2. Calculate days passed
            $daysPassed = now()->diffInDays($lastVerified);
            
            Log::info("OTP Check: User {$user->id} last verified {$daysPassed} days ago.");

            // 3. Trigger if 15 or more days
            if ($daysPassed >= 15) {
                return response()->json([
                    'message' => 'OTP_REQUIRED',
                    'days_passed' => $daysPassed,
                    'action' => 'Please call /api/otp/verify'
                ], 428); 
            }
        }
        return $next($request);
    }
}
