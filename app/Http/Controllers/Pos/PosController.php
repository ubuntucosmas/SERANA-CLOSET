<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\TillSession;
use App\Support\CustomOrderOptions;
use App\Support\PosOperator;
use App\Support\Role;
use App\Support\ShopPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The till screen (design §15.3). It loads locked or unlocked; everything
 * after that is JSON against the till endpoints, each as the PIN's operator.
 */
class PosController extends Controller
{
    public function index(Request $request)
    {
        $operator = PosOperator::current($request);
        $shop = Location::query()->online()->first();
        $open = $shop ? TillSession::where('location_id', $shop->id)->where('status', 'open')->with('openedBy:id,name')->orderBy('register')->get() : collect();

        return Inertia::render('Pos/Till', [
            'operator' => $operator ? ['id' => $operator->id, 'name' => $operator->name, 'role' => $operator->role,
                'can_approve' => Role::allows($operator->role, 'pos.approve')] : null,
            'shop' => $shop?->name,
            'tills' => $open->map(fn (TillSession $t) => ['id' => $t->id, 'register' => $t->register, 'opening_float' => $t->opening_float,
                'opened_by' => $t->openedBy?->name, 'opened_at' => $t->opened_at?->copy()->timezone(config('app.display_timezone'))->format('d M, H:i')])->values(),
            'policy' => [
                'hold_minutes' => ShopPolicy::posHoldMinutes(), 'idle_minutes' => ShopPolicy::posIdleMinutes(),
                'discount_percent' => ShopPolicy::cashierDiscountPercent(), 'discount_cap' => ShopPolicy::cashierDiscountCap(),
                'variance_alert' => ShopPolicy::tillVarianceAlert(),
            ],
            'garments' => array_column(CustomOrderOptions::forCustomers()['garments'], 'label'),
        ]);
    }
}
