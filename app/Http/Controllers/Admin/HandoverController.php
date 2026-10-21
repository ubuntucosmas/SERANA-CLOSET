<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Audit;
use Illuminate\Http\Request;

class HandoverController extends Controller
{
    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'address' => ['required_if:accepts_pickup,true', 'nullable', 'string', 'max:255'],
            'accepts_pickup' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:255'],
        ]);
        if ($data['accepts_pickup'] && trim($data['address'] ?? '') === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['address' => 'Give customers the exact shop address before offering collection.']);
        }
        $changes = ['name' => trim($data['name']), 'address' => trim($data['address'] ?? ''), 'accepts_pickup' => $data['accepts_pickup']];
        \Illuminate\Support\Facades\DB::transaction(function () use ($location, $changes, $data) {
            Audit::changes('handover.shop_updated', $location, $changes, $data['reason']);
            $location->update($changes);
        });
        return back()->with('success', 'Collection shop saved. New orders use these details; existing orders keep their saved address.');
    }
}
