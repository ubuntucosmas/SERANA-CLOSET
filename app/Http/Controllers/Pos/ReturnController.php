<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Services\ReturnService;
use App\Services\TillService;
use App\Support\PosOperator;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Find a sale and take goods back (design §15.4), as the PIN's operator; a cashier needs a manager. */
class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns, private TillService $tills)
    {
    }

    public function find(Request $request)
    {
        $q = strtoupper(trim((string) $request->query('q', '')));
        $order = CustomOrder::where('cipher', $q)->first();
        abort_unless($order, 404, 'No sale with that reference.');

        return response()->json(['order' => $this->returns->present($order)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:custom_orders,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.disposition' => ['required', 'in:restock,write_off'],
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', 'in:' . implode(',', ReturnService::METHODS)],
            'refund_reference' => ['required_if:refund_method,mpesa_manual', 'nullable', 'string', 'max:100'],
            'approval_pin' => ['nullable', 'string'],
            'register' => ['sometimes', 'string', 'max:40'],
        ], ['reason.required' => 'Say why it came back.', 'refund_reference.required_if' => 'Type the M-Pesa code of the refund you sent.']);
        $by = $request->attributes->get('pos_operator');
        $approver = null;
        if (! Role::allows($by->role, 'pos.approve')) {
            if (empty($data['approval_pin'])) {
                throw ValidationException::withMessages(['approval_pin' => 'A return needs a manager’s PIN.']);
            }
            $approver = PosOperator::approver($data['approval_pin']);
        }
        $order = CustomOrder::findOrFail($data['order_id']);
        $result = $this->returns->process($order, $this->tills->current(null, $data['register'] ?? 'Till 1'), $by, $data['lines'], $data['reason'],
            $data['refund_method'], $data['refund_reference'] ?? null, $approver);

        return response()->json($result + ['order' => $this->returns->present($order->fresh())]);
    }
}
