<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\ThemeSetting;
use App\Support\WhatsApp;
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
                $optimizer = app(\App\Services\ImageOptimizer::class);
                foreach ($request->file('images') as $image) {
                    $imagePaths[] = $optimizer->store($image, 'inspiration_images');
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

            // 2. Build the atelier brief and the click-to-chat link to the
            //    store number the admin configured in the studio dashboard
            //    (falling back to the app default). The same string is what the
            //    client previews and sends, so it reads warm yet stays scannable.
            $message = $this->buildBrief($order, $validated, $priorityPass, $imagePaths);

            $storePhone = ThemeSetting::get('whatsapp_number', config('services.whatsapp.number'));
            $whatsappUrl = WhatsApp::link($storePhone, $message);

            return response()->json([
                'success' => true,
                'whatsapp_url' => $whatsappUrl,
                'reference' => $order->cipher,
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
    }

    /**
     * Compose the WhatsApp commission brief.
     *
     * Formatted for a phone screen: WhatsApp *bold* headers, only the lines
     * that carry real information (no walls of "N/A"), and a clear Priority
     * Pass flag so the atelier never misses an expedited request. Optional
     * sections collapse gracefully when the client leaves them blank.
     */
    private function buildBrief(CustomOrder $order, array $validated, bool $priorityPass, array $imagePaths): string
    {
        $rule = "──────────────────";
        $lines = [];

        $lines[] = "✨ *SERANA ATELIER*";
        $lines[] = "_Bespoke Commission Brief_";
        $lines[] = "Ref {$order->cipher} · Order #{$order->id}";
        $lines[] = $rule;

        // — Client —
        $lines[] = "\n*Client*";
        $lines[] = "• {$order->full_name}";
        $lines[] = "• {$order->whatsapp_number}";
        if (!empty($validated['location'])) {
            $lines[] = "• {$validated['location']}";
        }

        // — The piece —
        $lines[] = "\n*The Piece*";
        $lines[] = "• " . strtoupper($order->type);
        if (!empty($validated['occasion'])) {
            $lines[] = "• Occasion · {$validated['occasion']}";
        }
        if (!empty($validated['deadline'])) {
            $lines[] = "• Needed by · {$validated['deadline']}";
        }
        if ($priorityPass) {
            $lines[] = "• ⚡ *Priority Pass* — expedited queue";
        }

        // — Measurements (only what was given) —
        $measurements = array_filter([
            'Height' => $validated['height_cm'] ?? null,
            'Bust'   => $order->bust_cm,
            'Waist'  => $order->waist_cm,
            'Hips'   => $order->hips_cm,
            'Length' => $order->length_cm,
        ], fn ($v) => $v !== null && $v !== '');

        $lines[] = "\n*Measurements*";
        if (empty($measurements)) {
            $lines[] = "_To be taken at the first fitting._";
        } else {
            foreach ($measurements as $label => $value) {
                $lines[] = "• {$label} · {$value} cm";
            }
        }

        // — Design —
        $lines[] = "\n*Design*";
        $lines[] = "• Fit · " . ($validated['selected_fit'] ?? 'Regular');
        $lines[] = "• Fabric · " . ($order->fabric_preference ?: 'To be discussed');
        $lines[] = "• Colour · " . ($validated['selected_color'] ?? 'To be discussed');

        // — Notes —
        if (!empty($validated['notes'])) {
            $lines[] = "\n*Notes*";
            $lines[] = "_{$validated['notes']}_";
        }

        // — References —
        if (count($imagePaths) > 0) {
            $lines[] = "\n*References* (" . count($imagePaths) . ")";
            $base = rtrim(config('app.url'), '/');
            foreach ($imagePaths as $index => $path) {
                $url = $base . '/storage/' . ltrim($path, '/');
                $lines[] = ($index + 1) . ". {$url}";
            }
        }

        $lines[] = "\n" . $rule;
        $lines[] = "Reply to confirm and we'll begin crafting. ✦";

        return implode("\n", $lines);
    }
}
