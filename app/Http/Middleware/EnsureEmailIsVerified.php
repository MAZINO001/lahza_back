<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next){
        if ($request->user() && $request->user()->status === 'waiting_confirmation') {
            return response()->json([
                'message' => 'Your email address is not verified. Please check your email for the verification link.',
                'status' => 'waiting_confirmation'
            ], 403);
        }

        return $next($request);
    }
}