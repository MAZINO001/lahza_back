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
    // Revoke the current user's token
    if ($request->user()) {
        $request->user()->currentAccessToken()->delete();
    }
    // If this is a web request, handle session cleanup
    if (! $request->wantsJson()) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
    return response()->noContent();
}
}
