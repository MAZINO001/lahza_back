<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailVerificationController extends Controller
{
    /**
     * Send verification email
     */
    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->status !== 'waiting_confirmation') {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        // Generate new token
        $user->email_verification_token = Str::random(64);
        $user->email_verification_sent_at = now();
        $user->save();

        // Send notification
        $user->notify(new EmailVerificationNotification());

        return response()->json(['message' => 'Verification email sent successfully']);
    }

    /**
     * Verify email with token
     */
    public function verify(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid verification token'], 400);
        }

        // Check if token expired (24 hours)
        if ($user->email_verification_sent_at->addHours(24)->isPast()) {
            return response()->json(['message' => 'Verification link has expired'], 400);
        }

        // Verify user
        $user->status = 'confirmed'; 
        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        return response()->json(['message' => 'Email verified successfully']);
    }

    /**
     * Resend verification email (public route for logged-out users)
     */
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)
                    ->where('status', 'waiting_confirmation')
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found or already verified'], 400);
        }

        // Generate new token
        $user->email_verification_token = Str::random(64);
        $user->email_verification_sent_at = now();
        $user->save();

        // Send notification
        $user->notify(new EmailVerificationNotification());

        return response()->json(['message' => 'Verification email resent successfully']);
    }
}