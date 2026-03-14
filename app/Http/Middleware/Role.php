<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Role
{
    /**
     * Usage: ->middleware('role:admin,hr')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $role = (string) ($user->role ?? 'admin');
        $allowed = array_filter(array_map('trim', $roles), fn ($r) => $r !== '');

        if (!$allowed || in_array($role, $allowed, true)) {
            return $next($request);
        }

        abort(403);
    }
}

