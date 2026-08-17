<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;

class MaintenanceModeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = Setting::get('maintenance_mode', 0);

        // Mode 1: Admin Maintenance Mode (Blocks standard roles, allows IT Admin & Super Admin)
        // Mode 2: Full IT Maintenance Mode (Blocks all roles except IT Admin)
        if ($mode == 1 || $mode == 2) {
            // Allow access to login, logout, maintenance page and health check
            if ($request->is('login') || $request->is('logout') || $request->is('maintenance') || $request->is('up')) {
                return $next($request);
            }

            // If user is logged in
            if (auth()->check()) {
                $user = auth()->user();

                // Mode 1: IT Admin & Super Admin allowed
                if ($mode == 1 && ($user->hasRole('IT Admin') || $user->role === 'Super Admin')) {
                    return $next($request);
                }

                // Mode 2: IT Admin ONLY allowed
                if ($mode == 2 && $user->hasRole('IT Admin')) {
                    return $next($request);
                }

                // Log out unauthorized users immediately
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('maintenance');
        }

        return $next($request);
    }
}
