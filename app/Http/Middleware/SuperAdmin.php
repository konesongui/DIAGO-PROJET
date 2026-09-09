<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        return $next($request);
    }
}
