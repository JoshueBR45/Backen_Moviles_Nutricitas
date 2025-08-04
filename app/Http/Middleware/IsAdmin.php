<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // Asegúrate de que 1 es el id del rol de admin en tu tabla 'roles'
        if (Auth::user() && Auth::user()->roles_id == 1) {
            return $next($request);
        }
        return response()->json(['message' => 'No autorizado'], 403);
    }
}