<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->must_change_password) {
            return $next($request);
        }

        $allowed = ['password.first.form', 'password.first.update', 'logout'];
        $routeName = $request->route()?->getName();
        if (is_string($routeName) && in_array($routeName, $allowed, true)) {
            return $next($request);
        }

        return redirect()->route('password.first.form');
    }
}

