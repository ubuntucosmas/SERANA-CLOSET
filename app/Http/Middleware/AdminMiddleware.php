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
        // Owners and managers (Role::MATRIX 'studio.access'); cashiers get the POS only.
        if (auth()->check() && auth()->user()->canUseStudio()) {
            return $next($request);
        }

        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please authenticate to access the Studio.');
        }

        // A cashier's workspace is the Sales Desk: send them there, not to the shop.
        if (auth()->user()->can('pos.sell')) {
            return redirect()->route('pos.index')->with('error', 'The Studio is for owners and managers. Your work is here at the Sales Desk.');
        }

        return redirect('/')->with('error', 'The Studio is for store staff only.');
    }
}
