<?php

namespace App\Http\Middleware;

use App\Support\PosOperator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Till actions need someone's PIN on the tablet (D12); the operator rides on the request. */
class RequirePosOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $operator = PosOperator::current($request);
        if (! $operator) {
            return response()->json(['message' => 'Enter your PIN.', 'locked' => true], 423);
        }
        $request->attributes->set('pos_operator', $operator);

        return $next($request);
    }
}
