<?php

namespace App\Http\Controllers;

use App\Support\SubmissionReceipt;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\ThemeSetting;
use App\Support\CustomOrderOptions;
use App\Support\WhatsApp;
use Illuminate\Validation\Rule;
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
            'baseProduct' => $targetProduct,
            // Garment, fabric and colour choices are managed in Studio → Settings.
            'options' => CustomOrderOptions::forCustomers(),
        ]);
    }

    public function store(Request $request)
    {
        // Only choices the studio currently offers are accepted.
        $allowed = CustomOrderOptions::allowedValues();

        $validated = $request->validate([
            'request_id' => 'nullable|uuid',
            'full_name' => 'required|string|max:255',
            // The only way the studio reaches the customer, and half of their tracking key.
            'whatsapp_number' => ['required', 'string', 'max:20', function ($attribute, $value, $fail) {
                $digits = \App\Support\Phone::normalize($value);
                if (strlen($digits) < 10 || strlen($digits) > 15) {
                    $fail('Enter the WhatsApp number we can reach you on, e.g. 07XX XXX XXX, or with the country code if you are outside Kenya.');
                }
            }],
            'location' => 'nullable|string|max:255',
            'outfit_type' => ['nullable', 'string', Rule::in($allowed['outfit_type'])],
            'fabric_preference' => ['nullable', 'string', Rule::in($allowed['fabric_preference'])],
            'selected_color' => ['nullable', 'string', Rule::in($allowed['selected_color'])],
            'notes' => 'nullable|string',
            'bust_cm' => 'nullable|numeric|between:30,250',
            'waist_cm' => 'nullable|numeric|between:30,250',
            'hips_cm' => 'nullable|numeric|between:30,250',
            'length_cm' => 'nullable|numeric|between:10,250',
            'height_cm' => 'nullable|numeric|between:50,250',
            'deadline' => 'nullable|date|after_or_equal:today',
            'occasion' => 'nullable|string|max:100',
            'selected_fit' => 'nullable|string|max:50',
            'images' => 'nullable|array|max:6',
            'images.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'priority_pass' => 'nullable',
        ], [
            'outfit_type.in' => 'That garment is no longer offered. Please choose another.',
            'fabric_preference.in' => 'That fabric is no longer offered. Please choose another.',
            'selected_color.in' => 'That colour is no longer offered. Please choose another.',
            'images.max' => 'You can attach up to 6 reference images.',
            'images.*.mimes' => 'Reference images must be JPG, PNG, WEBP or PDF. On iPhone, share the photo as JPG.',
            'images.*.max' => 'Each reference image must be 10MB or smaller.',
            'images.*.uploaded' => 'A reference image could not be uploaded. Try a smaller photo (under 10MB).',
        ]);

        if ($saved = SubmissionReceipt::recall($request, 'bespoke', $validated)) return response()->json($saved);

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
                'whatsapp_number' => \App\Support\Phone::normalize($validated['whatsapp_number']),
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

            $whatsappUrl = \App\Support\StoreProfile::whatsappLink($message);

            // 3. Alert the owner: the brief reaches WhatsApp only if the
            //    customer presses Send there.
            self::alertOwner($order, $message);

            return response()->json(SubmissionReceipt::remember($request, 'bespoke', $validated, [
                'success' => true,
                'whatsapp_url' => $whatsappUrl,
                'reference' => $order->cipher,
            ]));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("🚨 [CustomOrder_Fault] " . $e->getMessage(), [
                'full_name' => $validated['full_name'],
                'trace'     => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not save your request, so nothing was sent to the studio. Please try again in a moment, or message us on WhatsApp.'
            ], 500);
        }
    }

    private static function alertOwner(CustomOrder $order, string $brief): void
    {
        $owner = \App\Support\StoreProfile::notificationEmail();
        if (! $owner) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Mail::to($owner)->queue(new \App\Mail\NewCustomOrderAlertMail($order, $brief));
        } catch (\Throwable $e) {
            // Never fail the brief over email; it's in the Studio either way.
            \Illuminate\Support\Facades\Log::warning('Custom order alert could not be queued.', ['order_id' => $order->id, 'exception' => get_class($e)]);
        }
    }

    /**
     * Compose the WhatsApp custom-order brief the client sends to the studio.
     *
     * Written in the client's voice (they press send), formatted for a phone
     * screen: WhatsApp *bold* section headers, only lines that carry real
     * information (no walls of "N/A"), and a clear Priority flag so the
     * studio never misses an expedited request. Optional sections collapse
     * gracefully when the client leaves them blank.
     */
    private function buildBrief(CustomOrder $order, array $validated, bool $priorityPass, array $imagePaths): string
    {
        $lines = [];

        $lines[] = "Hello Serana,";
        $lines[] = "";
        $lines[] = "I'd like to place a custom order. Here is my brief:";
        $lines[] = "";
        $lines[] = "*Reference:* {$order->cipher}";

        // — Client —
        $lines[] = "\n*Client*";
        $lines[] = "Name: {$order->full_name}";
        $lines[] = 'Phone: ' . (\App\Support\StoreProfile::display((string) $order->whatsapp_number) ?: $order->whatsapp_number);
        if (!empty($validated['location'])) {
            $lines[] = "Location: {$validated['location']}";
        }

        // — The piece —
        $lines[] = "\n*The Piece*";
        $lines[] = "Type: " . ucfirst($order->type);
        if (!empty($validated['occasion'])) {
            $lines[] = "Occasion: {$validated['occasion']}";
        }
        if (!empty($validated['deadline'])) {
            $lines[] = "Needed by: {$validated['deadline']}";
        }
        if ($priorityPass) {
            $lines[] = "*Priority Pass* — expedited request";
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
            $lines[] = "To be taken at the first fitting.";
        } else {
            foreach ($measurements as $label => $value) {
                $lines[] = "{$label}: {$value} cm";
            }
        }

        // — Design —
        $lines[] = "\n*Design*";
        $lines[] = "Fit: " . ($validated['selected_fit'] ?? 'Regular');
        $lines[] = "Fabric: " . ($order->fabric_preference ?: 'To be discussed');
        $lines[] = "Colour: " . ($validated['selected_color'] ?? 'To be discussed');

        // — Notes —
        if (!empty($validated['notes'])) {
            $lines[] = "\n*Notes*";
            $lines[] = $validated['notes'];
        }

        // — References —
        if (count($imagePaths) > 0) {
            $lines[] = "\n*Reference images* (" . count($imagePaths) . ")";
            // Wherever the public disk lives (local /storage, S3, R2): the configured URL.
            foreach ($imagePaths as $index => $path) {
                $lines[] = ($index + 1) . '. ' . \App\Support\PublicUrl::absolute($path);
            }
        }

        $lines[] = "\nLooking forward to your confirmation. Thank you.";

        return implode("\n", $lines);
    }
}
