<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
 public function store(LoginRequest $request): JsonResponse
    {
        // return dd();
    $request->authenticate();
    $user = $request->user();
      if (is_null($user->last_otp_verified_at)) {
            $user->update([
                'last_otp_verified_at' => now()
            ]);
        }
    $user->tokens()->delete(); 

    $token = $user->createToken('auth-token')->plainTextToken;
    
    return response()->json([
        'user' => $user,
        'token' => $token,
        'token_type' => 'Bearer'
    ]);

    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
{
     $user = $request->user();

    if ($user) {
        try {
            $user->tokens()->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to revoke tokens'], 500);
        }
    }

    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Logged out successfully']);
}
}
