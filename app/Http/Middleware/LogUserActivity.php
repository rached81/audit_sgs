<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\UserLog;

class LogUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Execute the request first
        $response = $next($request);

        // Only log if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();

            // Filter out sensitive data
            $params = $request->except(['password', 'password_confirmation', '_token']);

            try {
                UserLog::create([
                    'user_id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'matricule' => $user->matricule,
                    'action' => $request->route() ? $request->route()->getName() : $request->path(),
                    'method' => $request->method(),
                    'parameters' => $params, // Casted to array/json in model
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                // Fail silently to not impact user experience
                // Log::error('Failed to log user activity: ' . $e->getMessage());
            }
        }

        return $response;
    }
}
