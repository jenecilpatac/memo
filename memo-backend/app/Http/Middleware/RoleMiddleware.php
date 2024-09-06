<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$roles)
    {
        
        $userRole = Auth::user()->role;

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Unauthorized to access',
                'user_role' => $userRole,
                'required_role' => $roles
            ], 403);
        }
    
        return $next($request);
    }
    

}
