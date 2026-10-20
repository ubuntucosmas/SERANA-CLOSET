<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Support\PosOperator;
use Illuminate\Http\Request;

class UnlockController extends Controller
{
    public function unlock(Request $request)
    {
        $data = $request->validate(['pin' => ['required', 'regex:/^\d{4,6}$/']], ['pin.regex' => 'That PIN doesn’t match anyone.']);
        $operator = PosOperator::unlock($request, $data['pin']);

        return response()->json(['operator' => ['id' => $operator->id, 'name' => $operator->name, 'role' => $operator->role,
            'can_approve' => \App\Support\Role::allows($operator->role, 'pos.approve')]]);
    }

    public function lock(Request $request)
    {
        PosOperator::lock($request);

        return response()->json(['locked' => true]);
    }
}
