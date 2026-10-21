<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;

/** Read-only, permission-gated investigation. Credentials and raw audit payloads never leave the server. */
class ActivityController extends Controller
{
    public static function data(Request $request): array
    {
        $filters = $request->validate([
            'h_type' => ['sometimes', 'in:actions,stock'], 'h_q' => ['nullable', 'string', 'max:120'],
            'h_from' => ['nullable', 'date_format:Y-m-d'], 'h_to' => ['nullable', 'date_format:Y-m-d', \Illuminate\Validation\Rule::when($request->filled('h_from'), 'after_or_equal:h_from')],
            'h_actor' => ['nullable', 'integer', 'exists:users,id'], 'h_page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $stock = ($filters['h_type'] ?? 'actions') === 'stock';
        $query = $stock ? StockMovement::with(['variant.product', 'location']) : AuditLog::query();
        $date = $stock ? 'occurred_at' : 'created_at';
        $zone = config('app.display_timezone', 'Africa/Nairobi');
        if ($filters['h_from'] ?? null) $query->where($date, '>=', \Carbon\Carbon::parse($filters['h_from'], $zone)->startOfDay()->utc());
        if ($filters['h_to'] ?? null) $query->where($date, '<=', \Carbon\Carbon::parse($filters['h_to'], $zone)->endOfDay()->utc());
        if ($filters['h_actor'] ?? null) $query->where('actor_type', 'user')->where('actor_id', $filters['h_actor']);
        if ($q = trim($filters['h_q'] ?? '')) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $orderIds = CustomOrder::withTrashed()->where('cipher', strtoupper($q))->pluck('id');
            $productIds = Product::withTrashed()->where('name', 'like', $like)->pluck('id');
            $query->where(function ($w) use ($stock, $like, $orderIds, $productIds) {
                $w->where('reason', 'like', $like)->orWhere($stock ? 'type' : 'action', 'like', $like);
                if ($stock) {
                    $w->orWhereIn('custom_order_id', $orderIds)->orWhereHas('variant', fn ($v) => $v->whereIn('product_id', $productIds)->orWhere('sku', 'like', $like));
                } else {
                    $w->orWhere(fn ($s) => $s->where('subject_type', (new CustomOrder)->getMorphClass())->whereIn('subject_id', $orderIds))
                        ->orWhere(fn ($s) => $s->where('subject_type', (new Product)->getMorphClass())->whereIn('subject_id', $productIds))
                        ->orWhere(fn ($s) => $s->where('actor_type', 'user')->whereIn('actor_id', User::where('name', 'like', $like)->select('id')));
                }
            });
        }
        $records = $query->orderByDesc($date)->orderByDesc('id')->paginate(30, ['*'], 'h_page');
        $actors = User::whereIn('id', $records->getCollection()->pluck('actor_id')->filter())->pluck('name', 'id');
        $rows = $records->getCollection()->map(function ($record) use ($stock, $actors, $zone) {
            $isOrder = ! $stock && $record->subject_type === (new CustomOrder)->getMorphClass();
            $isProduct = ! $stock && $record->subject_type === (new Product)->getMorphClass();
            $orderId = $stock ? $record->custom_order_id : ($isOrder ? $record->subject_id : null);
            $order = $orderId ? CustomOrder::withTrashed()->find($orderId) : null;
            $product = $stock ? $record->variant?->product : ($isProduct ? Product::withTrashed()->find($record->subject_id) : null);
            // Allow-list business changes; never expose PIN/password/token settings, IPs or arbitrary JSON.
            $fields = ['role', 'status', 'fulfilment_status', 'payment_status', 'resolution', 'amount', 'price_quoted', 'deposit', 'deposit_override', 'track_stock', 'quantity', 'on_hand', 'active', 'suspended_at', 'password_changed', 'register'];
            if (! $stock && $record->subject_type === (new \App\Models\Location)->getMorphClass()) $fields = [...$fields, 'name', 'address', 'accepts_pickup'];
            $changes = [];
            if (! $stock) foreach ($fields as $field) {
                $before = $record->before[$field] ?? null; $after = $record->after[$field] ?? null;
                if ($before !== $after && (is_scalar($before) || $before === null) && (is_scalar($after) || $after === null)) $changes[] = ['field' => str_replace('_', ' ', $field), 'before' => $before, 'after' => $after];
            }
            return [
                'id' => $record->id, 'at' => ($stock ? $record->occurred_at : $record->created_at)?->timezone($zone)->format('d M Y H:i'),
                'actor' => $record->actor_type === 'user' ? ($actors[$record->actor_id] ?? 'Former staff') : ucfirst($record->actor_type),
                'action' => $stock ? $record->type : $record->action, 'reason' => $record->reason ?: 'No reason recorded',
                'subject' => $order?->cipher ?? $product?->name ?? ($stock ? 'Stock item' : class_basename($record->subject_type ?: 'Store') . ($record->subject_id ? ' #' . $record->subject_id : '')),
                'size' => $stock ? $record->variant?->label() : null, 'quantity' => $stock ? $record->quantity : null,
                'where' => $stock ? $record->location?->name : (str_starts_with($record->action, 'pos.') || str_starts_with($record->action, 'till.') ? 'Shop till' : 'Studio / system'), 'changes' => $changes,
                'url' => $order ? route('studio.index', ['tab' => 'orders', 'order' => $order->id]) : ($product ? route('studio.index', ['tab' => 'stock']) : (! $stock && $record->subject_type === (new User)->getMorphClass() && request()->user()->can('staff.manage') ? route('studio.index', ['tab' => 'staff']) : (! $stock && $record->subject_type === (new \App\Models\TillSession)->getMorphClass() && \App\Models\TillSession::whereKey($record->subject_id)->exists() ? route('studio.till.report', $record->subject_id) : null))),
            ];
        });
        return ['rows' => $rows->values(), 'page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'total' => $records->total(), 'filters' => $filters,
            'actors' => User::whereIn('id', AuditLog::where('actor_type', 'user')->whereNotNull('actor_id')->select('actor_id'))
                ->orWhereIn('id', StockMovement::where('actor_type', 'user')->whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(['id', 'name']),
        ];
    }
}
