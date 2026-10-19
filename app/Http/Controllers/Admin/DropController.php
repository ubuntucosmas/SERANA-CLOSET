<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Drop;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DropController extends Controller
{
    public function __construct(private ImageOptimizer $optimizer) {}

    public function store(Request $request)
    {
        $data = $this->validateDrop($request);
        unset($data['image']); // file handled separately; not a column

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->optimizer->store($request->file('image'), 'drops');
        }

        Drop::create($data);

        return back()->with('success', 'Drop created.');
    }

    public function update(Request $request, Drop $drop)
    {
        $data = $this->validateDrop($request);
        unset($data['image']); // file handled separately; not a column

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->optimizer->store($request->file('image'), 'drops');
        }

        $drop->update($data);

        return back()->with('success', 'Drop updated.');
    }

    public function destroy(Drop $drop)
    {
        $drop->delete();

        return back()->with('success', 'Drop archived.');
    }

    private function validateDrop(Request $request): array
    {
        return $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            // Whole shillings, as in the catalogue: M-Pesa and the ledger cannot hold cents.
            'price'           => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.0{1,2})?$/'],
            'target_quantity' => 'required|integer|min:1',
            'ends_at'         => 'nullable|date',
            'status'          => ['required', Rule::in(['draft', 'live', 'greenlit', 'fulfilled', 'closed'])],
            'sizes'           => 'nullable|array',
            'sizes.*'         => 'string|max:20',
            'image'           => 'nullable|image|max:5120',
        ], ['price.regex' => 'Use whole shillings, e.g. 2500. M-Pesa cannot charge cents.']);
    }
}
