<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use App\Support\CustomOrderOptions;
use App\Support\PaymentSettings;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    private const ASSETS = ['site_logo', 'site_favicon', 'hero_bg', 'hero_detail_bg', 'focal_1_bg', 'focal_2_bg', 'focal_3_bg'];

    private const TEXT_RULES = [
        'site_name' => 'nullable|string|max:100',
        'site_tagline' => 'nullable|string|max:300',
        'whatsapp_number' => 'nullable|string|max:30|regex:/^[+\d\s()-]+$/',
        'focal_1_label' => 'nullable|string|max:200',
        'focal_2_label' => 'nullable|string|max:200',
        'focal_3_label' => 'nullable|string|max:200',
        'contact_email' => 'nullable|email|max:255',
        'contact_address' => 'nullable|string|max:500',
        'instagram_url' => 'nullable|url:http,https|max:500',
        'tiktok_url' => 'nullable|url:http,https|max:500',
        'facebook_url' => 'nullable|url:http,https|max:500',
        'x_url' => 'nullable|url:http,https|max:500',
        'care_phone' => 'nullable|string|max:30|regex:/^[+\d\s()-]+$/',
        'notification_email' => 'nullable|email|max:255',
    ];

    public function update(Request $request)
    {
        $request->validate(['key' => ['required', Rule::in(array_merge(self::ASSETS, array_keys(self::TEXT_RULES)))]]);
        $key = $request->string('key')->toString();
        if (in_array($key, self::ASSETS, true)) {
            // MIME validation rejects executable/SVG content. Keep the previous asset
            // until the replacement has been stored successfully.
            $request->validate(['value' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
            $disk = config('filesystems.public_disk');
            $path = $request->file('value')->store('themes', $disk);
            abort_unless(is_string($path) && $path !== '', 503, 'The image could not be stored. Please try again.');
            $old = ThemeSetting::where('key', $key)->value('value');
            ThemeSetting::set($key, $path);
            if (is_string($old) && str_starts_with($old, 'themes/')) Storage::disk($disk)->delete($old);
            \App\Support\Audit::record('settings.store.updated', null, [$key => $old], [$key => $path]);
        } else {
            $value = $request->validate(['value' => self::TEXT_RULES[$key]])['value'] ?? null;
            $this->saveText([$key => $value]);
        }
        return back()->with('success', 'Store setting updated.');
    }

    public function bulkUpdate(Request $request)
    {
        $rules = ['settings' => ['required', 'array:'.implode(',', array_keys(self::TEXT_RULES))]];
        foreach (self::TEXT_RULES as $key => $rule) $rules['settings.'.$key] = $rule;
        $settings = $request->validate($rules)['settings'];
        $this->saveText($settings);
        return back()->with('success', 'Store settings updated.');
    }

    /**
     * Save store text settings and record what changed: these say where customers
     * send orders and money (WhatsApp) and where owner alerts go.
     */
    private function saveText(array $settings): void
    {
        $before = [];
        $after = [];
        DB::transaction(function () use ($settings, &$before, &$after) {
            foreach ($settings as $key => $value) {
                $value = in_array($key, ['whatsapp_number', 'care_phone'], true) ? WhatsApp::normalizeNumber($value) : $value;
                $old = ThemeSetting::where('key', $key)->value('value');
                if ((string) $old === (string) $value) continue;
                ThemeSetting::set($key, $value);
                $before[$key] = $old;
                $after[$key] = $value;
            }
            if ($after) \App\Support\Audit::record('settings.store.updated', null, $before, $after);
        });
    }

    /** Studio → Settings → Shop (Phase E): discount limit, till variance alert, holds, idle lock, receipt footer. */
    public function updateShopPolicy(Request $request)
    {
        $validated = $request->validate(\App\Support\ShopPolicy::rules(), [
            'cashier_discount_percent.between' => 'A cashier discount is a share of the sale, from 0 to 100%.',
            'pos_hold_minutes.between' => 'A counter sale holds stock for 5 to 240 minutes.',
            'pos_idle_minutes.between' => 'The till locks after 1 to 120 idle minutes.',
        ]);
        $before = \App\Support\ShopPolicy::forStudio();
        \App\Support\ShopPolicy::save($validated);
        \App\Support\Audit::record('settings.shop_policy.updated', null, $before, \App\Support\ShopPolicy::forStudio());

        return back()->with('success', 'Shop settings updated.');
    }

    /** Studio → Settings → Orders: deposit share, pay-link lifetime and the deposit rule. */
    public function updateOrderPolicy(Request $request)
    {
        $validated = $request->validate(\App\Support\OrderPolicy::rules(), [
            'deposit_percent.between' => 'The deposit is a share of the quote, from 0 to 100%.',
            'pay_link_days.between' => 'A pay link can last from 1 to 90 days.',
            'refund_working_days.between' => 'Promise refunds within 1 to 14 working days.',
            'balance_hold_hours.between' => 'Hold a short-paid order’s stock for 1 to 168 hours (7 days).',
            'resolution_days.between' => 'A payment problem can wait from 1 to 30 days before it becomes a refund.',
        ]);
        $before = \App\Support\OrderPolicy::forStudio();
        \App\Support\OrderPolicy::save($validated);
        \App\Support\Audit::record('settings.order_policy.updated', null, $before, \App\Support\OrderPolicy::forStudio());

        return back()->with('success', 'Order policy updated.');
    }

    /** Studio → Settings → Payments: M-Pesa and WhatsApp ordering switches. */
    public function updatePaymentSettings(Request $request)
    {
        $validated = $request->validate(PaymentSettings::rules());

        if (! $validated['mpesa_enabled'] && ! $validated['whatsapp_enabled']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['whatsapp_enabled' => 'Keep at least one way to pay switched on.']);
        }
        if ($validated['mpesa_enabled'] && ! PaymentSettings::mpesaConfigured()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['mpesa_enabled' => 'M-Pesa can’t be switched on until its Daraja credentials are set on the server.']);
        }
        if (! $validated['mpesa_enabled'] && $validated['whatsapp_enabled'] && blank($validated['instructions'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['instructions' => 'Tell customers how to pay for WhatsApp orders (e.g. your Send Money number).']);
        }

        $before = array_intersect_key(PaymentSettings::forStudio(), $validated);
        PaymentSettings::save($validated);
        \App\Support\Audit::record('settings.payments.updated', null, $before, $validated);

        return back()->with('success', 'Payment options updated.');
    }

    /** Studio → Payments → "Test connection": can we get a Daraja token right now? */
    public function testMpesa(\App\Services\MpesaService $mpesa)
    {
        if (! PaymentSettings::mpesaConfigured()) {
            return response()->json(['ok' => false, 'message' => 'Daraja credentials are not set on the server.']);
        }
        $ok = (bool) $mpesa->getAccessToken();

        return response()->json([
            'ok' => $ok,
            'env' => config('services.mpesa.env') === 'production' ? 'live' : 'sandbox',
            'message' => $ok ? 'Connected to Safaricom.' : 'Safaricom did not accept the credentials or could not be reached. Check the logs.',
        ]);
    }

    /** Studio → Settings → Custom order options (garments, fabrics, colours). */
    public function updateCustomOrderOptions(Request $request)
    {
        $validated = $request->validate(CustomOrderOptions::rules(), [
            'fabrics.*.distinct' => 'Each fabric can only be listed once.',
            'colours.*.name.distinct' => 'Each colour can only be listed once.',
            'colours.*.hex.regex' => 'Pick each colour with the colour picker.',
        ]);

        if (! collect($validated['garments'])->contains('enabled', true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['garments' => 'Keep at least one garment switched on.']);
        }

        $before = CustomOrderOptions::all();
        CustomOrderOptions::save($validated);
        \App\Support\Audit::record('settings.custom_order_options.updated', null, $before, CustomOrderOptions::all());

        return back()->with('success', 'Custom order options updated.');
    }

    public function destroy(string $key)
    {
        abort_unless(in_array($key, self::ASSETS, true), 404);
        $setting = ThemeSetting::where('key', $key)->first();
        if ($setting) {
            if (is_string($setting->value) && str_starts_with($setting->value, 'themes/')) {
                Storage::disk(config('filesystems.public_disk'))->delete($setting->value);
            }
            $setting->delete();
        }
        return back()->with('success', 'Image reset to its default.');
    }
}
