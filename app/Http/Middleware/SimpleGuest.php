<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimpleGuest
{
    public function handle(Request $request, Closure $next)
    {
        $loggedIn = Auth::check();

        if ($loggedIn) {
            return redirect()->route('index');
        }

        return $next($request);
    }
}
