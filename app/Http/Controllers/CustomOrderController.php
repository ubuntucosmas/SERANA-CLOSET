<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomOrderController extends Controller
{
    /**
     * Display the Custom Order (Studio) page.
     */
    public function index(Request $request)
    {
        $targetProduct = null;
        if ($request->has('product')) {
            $targetProduct = Product::where('slug', $request->product)->first();
        }

        return Inertia::render('CustomOrder', [
            'showcaseProducts' => Product::latest()->take(6)->get(),
            'baseProduct' => $targetProduct
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'whatsapp_number' => 'required|string|max:20',
            'location' => 'nullable|string|max:255',
            'outfit_type' => 'nullable|string|max:100',
            'fabric_preference' => 'nullable|string|max:100',
            'selected_color' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'bust_cm' => 'nullable|numeric',
            'waist_cm' => 'nullable|numeric',
            'hips_cm' => 'nullable|numeric',
            'length_cm' => 'nullable|numeric',
            'height_cm' => 'nullable|numeric',
            'deadline' => 'nullable|string',
            'occasion' => 'nullable|string|max:100',
            'selected_fit' => 'nullable|string|max:50',
            'images.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'priority_pass' => 'nullable',
        ]);

        $priorityPass = filter_var($request->priority_pass, FILTER_VALIDATE_BOOLEAN);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('inspiration_images', 'public');
            }
        }

        $orderData = [
            'full_name' => $validated['full_name'],
            'whatsapp_number' => $validated['whatsapp_number'],
            'fabric_preference' => $validated['fabric_preference'] ?? null,
            'bust_cm' => $validated['bust_cm'] ?? null,
            'waist_cm' => $validated['waist_cm'] ?? null,
            'hips_cm' => $validated['hips_cm'] ?? null,
            'length_cm' => $validated['length_cm'] ?? null,
            'inspiration_image_paths' => $imagePaths,
            'user_id' => auth()->id(),
            'type' => $validated['outfit_type'] ?? 'custom',
            'priority_pass' => $priorityPass,
            'items_json' => [
                'location' => $validated['location'] ?? null,
                'selected_color' => $validated['selected_color'] ?? null,
                'notes' => $validated['notes'] ?? '',
                'deadline' => $validated['deadline'] ?? null,
                'occasion' => $validated['occasion'] ?? null,
                'selected_fit' => $validated['selected_fit'] ?? null,
                'height_cm' => $validated['height_cm'] ?? null,
                'source_product' => $request->product
            ]
        ];

        $order = CustomOrder::create($orderData);

        // Construct Expanded Artisan Narrative
        $storePhone = config('services.whatsapp.number');
        $message = "🏁 *NEW CUSTOM ARTISAN BRIEF* 🏁\n\n";
        $message .= "*[ 01: IDENTITY ]*\n";
        $message .= "• Name: {$order->full_name}\n";
        $message .= "• WhatsApp: {$order->whatsapp_number}\n";
        if ($validated['location']) {
            $message .= "• Location: {$validated['location']}\n";
        }
        
        $message .= "\n*[ 02: CATEGORY & CONTEXT ]*\n";
        $message .= "• Garment: {$order->type}\n";
        if ($validated['occasion']) {
            $message .= "• Event: {$validated['occasion']}\n";
        }
        if ($validated['deadline']) {
            $message .= "• Target Date: {$validated['deadline']}\n";
        }

        $message .= "\n*[ 03: TECHNICAL SPECS ]*\n";
        $message .= "• Height: " . ($validated['height_cm'] ?? 'N/A') . " cm\n";
        $message .= "• Bust: " . ($order->bust_cm ?? 'N/A') . " cm\n";
        $message .= "• Waist: " . ($order->waist_cm ?? 'N/A') . " cm\n";
        $message .= "• Hips: " . ($order->hips_cm ?? 'N/A') . " cm\n";
        $message .= "• Length: " . ($order->length_cm ?? 'N/A') . " cm\n";

        $message .= "\n*[ 04: DESIGN FOUNDATION ]*\n";
        $message .= "• Fit: " . ($validated['selected_fit'] ?? 'Regular') . "\n";
        $message .= "• Fabric: " . ($order->fabric_preference ?? 'To be discussed') . "\n";
        $message .= "• Color: " . ($validated['selected_color'] ?? 'To be discussed') . "\n";

        if ($validated['notes']) {
            $message .= "\n*[ DESIGNER NOTES ]*\n";
            $message .= "{$validated['notes']}\n";
        }

        if (count($imagePaths) > 0) {
            $message .= "\n*[ INSPIRATION ATTACHED ]*\n";
            $message .= "• " . count($imagePaths) . " image(s) uploaded.\n";
            foreach($imagePaths as $index => $path) {
                // Generate full URL
                $message .= "• Link " . ($index + 1) . ": " . asset('storage/' . $path) . "\n";
            }
        }

        $message .= "\n--------------------------\n";
        $message .= "Order ID: #{$order->id}\n";
        if ($priorityPass) {
            $message .= "*⚡ [ PRIORITY PRODUCTION CLAIMED ] * ⚡\n";
        }
        $message .= "--------------------------\n";
        $message .= "Sent via Serana Digital Atelier Protocol.";

        $whatsappUrl = "https://wa.me/{$storePhone}?text=" . rawurlencode($message);

        return response()->json([
            'success' => true,
            'whatsapp_url' => $whatsappUrl
        ]);
    }
}
