<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\OrderProgressSnap;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

use App\Models\Product;
use App\Models\Category;
use App\Models\GalleryImage;

use App\Models\Lead;
use App\Models\ThemeSetting;
use App\Models\Drop;
use App\Support\OrderStatus;
use Carbon\Carbon;

class StudioController extends Controller
{
    public function index(Request $request)
    {
        $weekAgo = Carbon::now()->subDays(7);

        // Production stages still being actively worked (not yet completed/shipped).
        $activeStages = array_values(array_diff(
            OrderStatus::PRODUCTION_STAGES,
            OrderStatus::PRODUCTION_TERMINAL
        ));

        // Lightweight, always-present payload for the Home screen.
        $stats = [
            'new_orders_week'   => CustomOrder::where('created_at', '>=', $weekAgo)->count(),
            // Money received in the last 7 days (archived orders still count).
            'revenue_week'      => (float) CustomOrder::withTrashed()->where('is_paid', true)
                                        ->where('paid_at', '>=', $weekAgo)->sum('price_quoted'),
            // Being made right now; new briefs awaiting a quote are not "in production".
            'in_production'     => CustomOrder::whereIn('status', array_values(array_diff($activeStages, ['pending'])))->count(),
            'subscribers_total' => Lead::count(),
        ];

        $attention = [
            'awaiting' => CustomOrder::where(function ($query) {
                $query->whereIn('status', [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::PAID, 'pending']);
            })->latest()->take(12)->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                    'amount' => $o->price_quoted,
                    'reference' => $o->cipher,
                    'next_action' => $o->is_paid ? 'Ready for fulfilment'
                        : ($o->status === OrderStatus::AMOUNT_MISMATCH ? 'Review payment amount'
                        : ($o->status === OrderStatus::PAYMENT_FAILED ? 'Follow up on failed payment'
                        : ($o->status === 'pending' ? 'Review custom order' : 'Awaiting payment confirmation'))),
                ]),
            // Switched off by staff, or a limited batch with two or fewer units left.
            'low_stock' => Product::where('in_stock', false)
                ->orWhere(fn ($q) => $q->whereNotNull('batch_limit')->whereRaw('batch_limit - COALESCE(batch_sold, 0) <= 2'))
                ->latest()->take(8)->get(['id', 'name', 'in_stock', 'batch_limit', 'batch_sold'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name,
                    'note' => ! $p->in_stock ? 'Marked out of stock' : max(0, $p->batch_limit - (int) $p->batch_sold) . ' left']),
        ];

        // A customer who paid twice is owed a refund; surface it until handled.
        $duplicatePaid = PaymentAttempt::where('status', PaymentAttempt::PAID)
            ->selectRaw('custom_order_id, COUNT(*) as paid_count')->groupBy('custom_order_id')
            ->havingRaw('COUNT(*) > 1')->pluck('paid_count', 'custom_order_id');
        if ($duplicatePaid->isNotEmpty()) {
            $refunds = CustomOrder::withTrashed()->whereIn('id', $duplicatePaid->keys())->get()->map(fn ($o) => [
                'id' => $o->id, 'full_name' => $o->full_name, 'amount' => $o->price_quoted, 'reference' => $o->cipher,
                'next_action' => 'Paid ' . $duplicatePaid[$o->id] . ' times: refund owed',
            ]);
            $attention['awaiting'] = $refunds->concat($attention['awaiting'])->values();
        }

        return Inertia::render('Admin/StudioDashboard', [
            'stats'      => $stats,
            'attention'  => $attention,
            'active_tab' => $request->get('tab', 'home'),

            // Eager so every list stays in sync after a save/redirect. The dataset
            // is boutique-sized; paginate here if a section ever grows large.
            'orders'            => CustomOrder::with(['user', 'progressSnaps'])->latest()->get(),
            'archived_orders'   => CustomOrder::onlyTrashed()->with(['user', 'progressSnaps'])->latest()->get(),
            'products'          => Product::with('category')->latest()->get(),
            'archived_products' => Product::onlyTrashed()->with('category')->latest()->get(),
            'categories'        => Category::all(),
            'gallery'           => GalleryImage::latest()->get(),
            'archived_gallery'  => GalleryImage::onlyTrashed()->latest()->get(),
            'leads'             => Lead::latest()->get(),
            'custom_order_options' => \App\Support\CustomOrderOptions::all(),
            'drops'             => Drop::with(['reservations' => fn ($q) => $q->latest()])
                                        ->withCount('paidReservations')->latest()->get(),
        ]);
    }

    public function update(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(array_merge(OrderStatus::PRODUCTION_STAGES, [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::EXPIRED]))],
            'internal_notes' => 'nullable|string',
            'price_quoted' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
        ]);

        if (array_key_exists('is_paid', $validated) && (bool) $validated['is_paid'] !== $order->is_paid) {
            throw \Illuminate\Validation\ValidationException::withMessages(['is_paid' => 'Payment is confirmed by the payment service and cannot be changed here.']);
        }
        $commerce = in_array($order->type, ['standard', 'drop'], true);
        if (($order->is_paid || $commerce) && array_key_exists('price_quoted', $validated) && (float) $validated['price_quoted'] !== (float) $order->price_quoted) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price_quoted' => 'A checkout or paid order’s total cannot be changed.']);
        }
        if ($validated['status'] !== $order->status && ! in_array($validated['status'], OrderStatus::PRODUCTION_STAGES, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Payment states are managed automatically. Choose a fulfilment stage.']);
        }
        if ($commerce && $validated['status'] !== $order->status) {
            if (! $order->is_paid || ! in_array($validated['status'], OrderStatus::PRODUCTION_STAGES, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Confirm payment before updating fulfilment. Payment states are managed automatically.']);
            }
        }
        unset($validated['is_paid']);
        $order->update($validated);

        return back()->with('success', 'Order updated successfully.');
    }

    /**
     * Bespoke work is quoted and paid over WhatsApp, outside the M-Pesa STK
     * flow. Staff record that payment here with the amount and a reference so
     * the customer's tracking and the Studio show it as paid. Shop orders and
     * Drop reservations can only be confirmed by M-Pesa settlement.
     */
    public function recordPayment(Request $request, CustomOrder $order)
    {
        if (in_array($order->type, ['standard', 'drop'], true)) {
            return back()->with('error', 'Shop orders and Drop reservations are confirmed automatically by M-Pesa.');
        }
        if ($order->is_paid) {
            return back()->with('error', 'This order is already marked as paid.');
        }
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:100'],
        ], [
            'amount.integer' => 'Enter the amount in whole shillings.',
            'reference.required' => 'Add the M-Pesa code or a short note about how this was paid.',
        ]);

        $note = sprintf('[%s] Payment recorded by %s: KSh %s, ref %s',
            now()->timezone(config('app.display_timezone'))->format('Y-m-d H:i'), $request->user()->name,
            number_format($data['amount']), $data['reference']);

        $claimed = CustomOrder::whereKey($order->id)->where('is_paid', false)->update([
            'is_paid' => true,
            'paid_at' => now(),
            'price_quoted' => $data['amount'],
            'mpesa_receipt_number' => mb_substr($data['reference'], 0, 100),
            'internal_notes' => trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . $note),
        ]);

        return back()->with($claimed ? 'success' : 'error', $claimed ? 'Payment recorded.' : 'This order is already marked as paid.');
    }

    public function destroy(CustomOrder $order)
    {
        if ($order->paymentAttempts()->where('status', PaymentAttempt::PENDING)->exists()) {
            return back()->with('error', 'This order has an M-Pesa payment still in progress. Archive it after the payment succeeds, fails or expires.');
        }
        if ($order->is_paid && ! in_array($order->status, OrderStatus::PRODUCTION_TERMINAL, true)) {
            return back()->with('error', 'Paid orders can be archived once they are completed or dispatched.');
        }

        $order->delete();
        return back()->with('success', 'Order moved to the archive.');
    }

    public function restore($id)
    {
        $order = CustomOrder::withTrashed()->findOrFail($id);
        $order->restore();
        return back()->with('success', 'Order restored to active registry.');
    }

    public function forceDelete($id)
    {
        $order = CustomOrder::withTrashed()->findOrFail($id);

        // Payment records are financial evidence: an order with any payment
        // history (or that was paid) is kept in the archive, never purged.
        if ($order->is_paid || $order->paymentAttempts()->exists()) {
            return back()->with('error', 'Orders with payment history cannot be permanently deleted. They stay in the archive as a record.');
        }

        // Clean up linked progress snaps imagery if they exist
        foreach ($order->progressSnaps as $snap) {
            if ($snap->image_path) {
                Storage::disk(config('filesystems.public_disk'))->delete($snap->image_path);
            }
        }
        
        $order->forceDelete();
        return back()->with('success', 'Order and associated visual assets permanently purged.');
    }

    public function storeProgressSnap(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            // Store optimized raw path; models resolve to full URL via dynamic disk logic.
            $validated['image_path'] = app(\App\Services\ImageOptimizer::class)
                ->store($request->file('image'), 'progress_snaps');
            $validated['custom_order_id'] = $order->id;
        }

        OrderProgressSnap::create($validated);

        return back()->with('success', 'Order photo uploaded.');
    }
}
