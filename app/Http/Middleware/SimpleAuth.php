<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimpleAuth
{
    public function handle(Request $request, Closure $next)
    {
        $loggedIn = Auth::check();

        if (!$loggedIn) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
