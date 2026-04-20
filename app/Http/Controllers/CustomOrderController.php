<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * CustomOrderController
 * 
 * Manages the "Digital Atelier" procurement flow.
 * This controller handles custom order requests, generates structured
 * production briefs for WhatsApp handoff, and persists client data.
 */
class CustomOrderController extends Controller
{
    /**
     * Display the Custom Order (Studio) page.
     * Optionally pre-fills the form if a source product slug is provided.
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

        try {
            $priorityPass = filter_var($request->priority_pass, FILTER_VALIDATE_BOOLEAN);

            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePaths[] = $image->store('inspiration_images', 'public');
                }
            }

            $userId = optional(auth()->user())->id;

            $orderData = [
                'full_name' => $validated['full_name'],
                'whatsapp_number' => $validated['whatsapp_number'],
                'fabric_preference' => $validated['fabric_preference'] ?? null,
                'bust_cm' => $validated['bust_cm'] ?? null,
                'waist_cm' => $validated['waist_cm'] ?? null,
                'hips_cm' => $validated['hips_cm'] ?? null,
                'length_cm' => $validated['length_cm'] ?? null,
                'inspiration_image_paths' => $imagePaths,
                'user_id' => $userId,
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

            // 1. Persist the Order to Database
            $order = CustomOrder::create($orderData);

            // 2. Construct Elite Artisan Brief (WhatsApp Handoff)
            // This string is formatted for high readability on small mobile screens
            // used by production teams and clients.
            $storePhone = config('services.whatsapp.number');
            $targetDisk = config('filesystems.public_disk');
            
            $message = "✨ *SERANA DIGITAL ATELIER | PRODUCTION BRIEF* ✨\n";
            $message .= "Order ID: #{$order->id} | Protocol: V4.2\n";
            $message .= "------------------------------------------\n\n";

            $message .= "👤 *[ CLIENT IDENTITY ]*\n";
            $message .= "• Name: {$order->full_name}\n";
            $message .= "• WhatsApp: {$order->whatsapp_number}\n";
            if ($validated['location']) {
                $message .= "• Residence: {$validated['location']}\n";
            }
            
            $message .= "\n👗 *[ GARMENT ARCHITECTURE ]*\n";
            $message .= "• Style: " . strtoupper($order->type) . "\n";
            if ($validated['occasion']) {
                $message .= "• Context/Event: {$validated['occasion']}\n";
            }
            if ($validated['deadline']) {
                $message .= "• Target Delivery: {$validated['deadline']}\n";
            }

            $message .= "\n📏 *[ PRECISION MEASUREMENTS ]*\n";
            $message .= "• Full Height: " . ($validated['height_cm'] ?? 'N/A') . " cm\n";
            $message .= "• Bust: " . ($order->bust_cm ?? 'N/A') . " cm\n";
            $message .= "• Waist: " . ($order->waist_cm ?? 'N/A') . " cm\n";
            $message .= "• Hips: " . ($order->hips_cm ?? 'N/A') . " cm\n";
            $message .= "• Drape Length: " . ($order->length_cm ?? 'N/A') . " cm\n";

            $message .= "\n🧵 *[ DESIGN SPECIFICATIONS ]*\n";
            $message .= "• Fit Preference: " . ($validated['selected_fit'] ?? 'Regular') . "\n";
            $message .= "• Fabric: " . ($order->fabric_preference ?? 'To be discussed') . "\n";
            $message .= "• Color: " . ($validated['selected_color'] ?? 'To be discussed') . "\n";

            if ($validated['notes']) {
                $message .= "\n📝 *[ DESIGNER NOTES ]*\n";
                $message .= "_{$validated['notes']}_\n";
            }

            if (count($imagePaths) > 0) {
                $message .= "\n🖼️ *[ INSPIRATION ARCHIVE ]*\n";
                $message .= "• Total Assets: " . count($imagePaths) . "\n";
                foreach($imagePaths as $index => $path) {
                    // Use dynamic disk URL (Handles S3/Local automatically)
                    $url = \Illuminate\Support\Facades\Storage::disk($targetDisk)->url($path);
                    $message .= "• Asset " . ($index + 1) . ": " . $url . "\n";
                }
            }

            $message .= "------------------------------------------\n";
            $message .= "CONFIRM RECEIPT TO BEGIN TAILORING.";

            // 3. Encode the message for safe URI transport
            $whatsappUrl = "https://wa.me/{$storePhone}?text=" . rawurlencode($message);

            return response()->json([
                'success' => true,
                'whatsapp_url' => $whatsappUrl
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("🚨 [CustomOrder_Fault] " . $e->getMessage(), [
                'full_name' => $validated['full_name'],
                'trace'     => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The digital atelier is momentarily busy. Please try again.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'whatsapp_url' => $whatsappUrl
        ]);
    }
}
