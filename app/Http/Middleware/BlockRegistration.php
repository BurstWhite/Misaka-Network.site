<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) admin_setting('stop_register', 0)) {
            abort(404);
        }

        return $next($request);
    }
}
