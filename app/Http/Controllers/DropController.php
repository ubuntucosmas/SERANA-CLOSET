<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Drop;
use App\Support\OrderStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public storefront for Serana Drops. Reserving a piece creates a CustomOrder
 * (type='drop'), then the existing M-Pesa STK flow (mpesa.stk + status polling)
 * takes the payment — so Drops inherit settlement, receipts and tracking for free.
 */
class DropController extends Controller
{
    /** Live campaigns the public can browse. */
    public function index()
    {
        $drops = Drop::whereIn('status', ['live', 'greenlit'])
            ->withCount('paidReservations')
            ->latest()
            ->get();

        return Inertia::render('Drops', ['drops' => $drops]);
    }

    /** A single drop. Drafts are never public. */
    public function show(Drop $drop)
    {
        abort_if($drop->status === 'draft', 404);

        $drop->loadCount('paidReservations');

        return Inertia::render('DropDetail', ['drop' => $drop]);
    }

    /**
     * Reserve a piece: create the pending reservation order and hand back its id
     * so the client can launch the standard M-Pesa STK push against it.
     */
    public function reserve(Request $request, Drop $drop)
    {
        if (! $drop->is_open) {
            return response()->json(['success' => false, 'message' => 'This drop is no longer open for reservations.'], 422);
        }
        if ((float) $drop->price <= 0) {
            return response()->json(['success' => false, 'message' => 'This drop has no price set yet.'], 422);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone'     => 'required|string|max:30',
            'email'     => 'nullable|email',
            'size'      => 'nullable|string|max:50',
        ]);

        $order = CustomOrder::create([
            'user_id'         => auth()->id(),
            'drop_id'         => $drop->id,
            'type'            => 'drop',
            'status'          => OrderStatus::PENDING_PAYMENT,
            'price_quoted'    => $drop->price,
            'is_paid'         => false,
            'full_name'       => $validated['full_name'],
            'phone'           => $validated['phone'],
            'whatsapp_number' => $validated['phone'],
            'email'           => $validated['email'] ?? null,
            'items_json'      => ['drop' => $drop->title, 'size' => $validated['size'] ?? null],
        ]);

        // Bind to the buyer's session so they (and only they) can pay & poll
        // status without logging in — same mechanism as standard checkout.
        $request->session()->push('serana.order_ids', $order->id);

        return response()->json([
            'success'  => true,
            'message'  => 'Reservation created. Proceed to payment.',
            'order_id' => $order->id,
        ]);
    }
}
