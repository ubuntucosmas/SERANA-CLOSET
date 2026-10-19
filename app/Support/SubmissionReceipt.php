<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Reuse a saved submission after a lost response; scoped to the buyer's session. */
final class SubmissionReceipt
{
    public static function recall(Request $request, string $scope, array $payload): ?array
    {
        $id = $payload['request_id'] ?? null;
        if (! $id) return null;
        $saved = $request->session()->get('serana.submissions.'.$id);
        if (! $saved) return null;
        if ($saved['scope'] !== $scope || $saved['hash'] !== self::hash($payload)) {
            throw ValidationException::withMessages(['request_id' => 'This request has already been saved with different details. Reload the page to start a new request.']);
        }
        return $saved['response'];
    }

    public static function remember(Request $request, string $scope, array $payload, array $response): array
    {
        if ($id = $payload['request_id'] ?? null) {
            $receipts = $request->session()->get('serana.submissions', []);
            $receipts[$id] = ['scope' => $scope, 'hash' => self::hash($payload), 'response' => $response];
            $request->session()->put('serana.submissions', array_slice($receipts, -20, null, true));
        }
        return $response;
    }

    private static function hash(array $payload): string
    {
        unset($payload['request_id'], $payload['images']);
        ksort($payload);
        return hash('sha256', json_encode($payload));
    }
}
