<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    /**
     * Send OTP code to user's email
     */
    public function sendCode(Request $request)
    {
        $user = $request->user();
        
        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Store hashed OTP and expiration
        $user->update([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        // Send OTP via email
        try {
            Mail::to($user->email)->send(new OtpMail($otp, $user->name));
            
            Log::info("OTP sent to user {$user->id} ({$user->email})");
            
            return response()->json([
                'message' => 'OTP sent successfully to your email.',
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send OTP to {$user->email}: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify the OTP code
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|numeric|digits:6'
        ]);

        $user = $request->user();

        // Check if OTP exists
        if (!$user->otp_code) {
            return response()->json([
                'message' => 'No OTP code found. Please request a new code.'
            ], 422);
        }

        // Check if OTP is expired
        if (now()->gt($user->otp_expires_at)) {
            return response()->json([
                'message' => 'OTP code has expired. Please request a new code.'
            ], 422);
        }

        // Verify OTP
        if (Hash::check($request->code, $user->otp_code)) {
            $user->update([
                'otp_code' => null,
                'otp_expires_at' => null,
                'last_otp_verified_at' => now(),
            ]);

            return response()->json([
                'message' => 'OTP verified successfully. Access granted.',
                'verified_at' => now()->toDateTimeString()
            ]);
        }

        return response()->json([
            'message' => 'Invalid OTP code. Please try again.'
        ], 422);
    }

    /**
     * Check if user needs OTP verification
     */
    public function checkStatus(Request $request)
    {
        $user = $request->user();
        $lastVerified = $user->last_otp_verified_at ?? $user->created_at;
        $daysPassed = (int) now()->diffInDays($lastVerified);
        $needsVerification = $daysPassed >= 15;

        return response()->json([
            'needs_verification' => $needsVerification,
            'days_passed' => $daysPassed,
            'last_verified' => $lastVerified,
            'next_verification' => $lastVerified->addDays(15)
        ]);
    }

    /**
     * Resend OTP code (for testing or if user didn't receive)
     */
    public function resendCode(Request $request)
    {
        $user = $request->user();

        // Check if previous OTP is still valid
        if ($user->otp_expires_at && now()->lt($user->otp_expires_at)) {
            $remainingTime = now()->diffInSeconds($user->otp_expires_at);
            
            return response()->json([
                'message' => 'An OTP was recently sent. Please wait before requesting a new one.',
                'can_resend_in' => $remainingTime . ' seconds'
            ], 429);
        }

        // Send new OTP
        return $this->sendCode($request);
    }
}