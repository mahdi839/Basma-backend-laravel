<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
        }

        if ($request->user()->hasRole('super-admin')) {
            return $next($request);
        }

        if (!$request->user()->hasPermissionTo($permission)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Missing permission: ' . $permission
            ], 403);
        }

        return $next($request);
    }
}
