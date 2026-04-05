<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'admin') {
            return $next($request);
        }

        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please authenticate to access the Studio.');
        }

        return redirect('/')->with('error', "Studio access restricted to authorized artisans only.");
    }
}
