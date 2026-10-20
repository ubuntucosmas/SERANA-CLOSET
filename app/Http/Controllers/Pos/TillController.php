<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\TillService;
use App\Support\PosOperator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Open, take cash out of, and close a till (design §15.2), as the PIN's operator. */
class TillController extends Controller
{
    public function __construct(private TillService $tills)
    {
    }

    private function operator(Request $request)
    {
        return $request->attributes->get('pos_operator');
    }

    public function open(Request $request)
    {
        $data = $request->validate(['register' => ['required', 'string', 'max:40'], 'float' => ['required', 'integer', 'min:0', 'max:10000000']]);
        $session = $this->tills->open(Location::query()->online()->firstOrFail(), $data['register'], (int) $data['float'], $this->operator($request));

        return response()->json(['till' => ['id' => $session->id, 'register' => $session->register, 'opening_float' => $session->opening_float]]);
    }

    public function payout(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'in:payout,pickup'], 'approval_pin' => ['required', 'string'], 'register' => ['sometimes', 'string', 'max:40']],
            ['approval_pin.required' => 'This needs a manager’s PIN.']);
        $approver = PosOperator::approver($data['approval_pin']);
        $movement = $this->tills->takeOut($this->tills->current(null, $data['register'] ?? 'Till 1'), $data['type'] ?? 'payout', (int) $data['amount'], $data['reason'], $this->operator($request), $approver);

        return response()->json(['movement' => ['id' => $movement->id, 'amount' => $movement->amount]]);
    }

    public function close(Request $request)
    {
        $data = $request->validate(['counted' => ['required', 'integer', 'min:0'], 'variance_reason' => ['nullable', 'string', 'max:255'],
            'approval_pin' => ['nullable', 'string'], 'register' => ['sometimes', 'string', 'max:40']]);
        $session = $this->tills->current(null, $data['register'] ?? 'Till 1');
        $variance = (int) $data['counted'] - $this->tills->expectedCash($session);
        $approver = null;
        if ($this->tills->needsAcknowledgement($variance)) {
            $missing = array_filter(['variance_reason' => trim((string) ($data['variance_reason'] ?? '')) === '' ? 'Say why the drawer is KSh ' . number_format(abs($variance)) . ($variance < 0 ? ' short.' : ' over.') : null,
                'approval_pin' => empty($data['approval_pin']) ? 'A difference this size needs a manager’s PIN.' : null]);
            if ($missing) {
                throw ValidationException::withMessages($missing);
            }
            $approver = PosOperator::approver($data['approval_pin']);
        }

        return response()->json(['report' => $this->tills->close($session, (int) $data['counted'], $this->operator($request), $data['variance_reason'] ?? null, $approver)]);
    }

    public function report(Request $request)
    {
        return response()->json(['report' => $this->tills->report($this->tills->current(null, $request->query('register', 'Till 1')))]);
    }
}
