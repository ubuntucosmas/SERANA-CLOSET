<?php

namespace Database\Seeders;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use Illuminate\Database\Seeder;

/**
 * Local QA scenarios for batch 2 Phases A and B, built through the real
 * services so lines, ledger, receipts and audit rows are genuine. Idempotent
 * (keyed by name). Uses the Safaricom sandbox test number, never a real one.
 *
 *   ddev exec php artisan db:seed --class=Batch2ScenarioSeeder
 */
class Batch2ScenarioSeeder extends Seeder
{
    private const PHONE = '254708374149';

    public function run(OrderSettlementService $settlement): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('QA scenarios are for local and testing only.');
        }

        $owner = User::where('role', 'owner')->orderBy('id')->firstOrFail();
        $product = Product::whereNull('deleted_at')->where('in_stock', true)->where('price', '>=', 1000)->orderBy('id')->firstOrFail();
        $price = (int) round((float) $product->price);

        // 1. A shop order paid in one go by M-Pesa.
        $this->once('QA-B Shop Paid', function ($name) use ($settlement, $product, $price) {
            $order = $this->shopOrder($name, $product, $price, 'mpesa');
            $attempt = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_QA_B_SHOP', 'phone' => self::PHONE, 'amount' => $price, 'status' => 'paid', 'mpesa_receipt_number' => 'QAB1SHOP01']);
            $settlement->recordPayment($order, $price, 'mpesa_stk', Actor::safaricom(), 'QAB1SHOP01', $attempt);
        });

        // 2. A WhatsApp order confirmed in the Studio.
        $this->once('QA-B WhatsApp Confirmed', function ($name) use ($settlement, $product, $price, $owner) {
            $order = $this->shopOrder($name, $product, $price, 'whatsapp');
            $settlement->recordPayment($order, $price, 'mpesa_manual', Actor::user($owner), 'QAB2TILL01');
        });

        // 3. Bespoke: deposit, then the balance.
        $this->once('QA-B Bespoke Full', function ($name) use ($settlement, $owner) {
            $order = $this->bespoke($name, 'designing');
            $settlement->setQuote($order, 12000, Actor::user($owner), 'Quote agreed on WhatsApp');
            $settlement->recordPayment($order, 6000, 'mpesa_stk', Actor::safaricom(), 'QAB3DEP001');
            $settlement->recordPayment($order->fresh(), 6000, 'mpesa_manual', Actor::user($owner), 'QAB3BAL001');
        });

        // 4. Bespoke: deposit only, balance outstanding.
        $this->once('QA-B Bespoke Deposit', function ($name) use ($settlement, $owner) {
            $order = $this->bespoke($name, 'designing');
            $settlement->setQuote($order, 9000, Actor::user($owner), 'Quote agreed on WhatsApp');
            $settlement->recordPayment($order, 4500, 'mpesa_manual', Actor::user($owner), 'QAB4DEP001');
        });

        // 5. A paid shop order, fulfilled, then archived.
        $this->once('QA-B Archived Paid', function ($name) use ($settlement, $product, $price) {
            $order = $this->shopOrder($name, $product, $price, 'mpesa');
            $settlement->recordPayment($order, $price, 'mpesa_stk', Actor::safaricom(), 'QAB5ARCH01');
            $order->fresh()->update(['status' => 'shipped']);
            $order->fresh()->delete();
        });

        // 6. For the browser walk-through: a bespoke brief waiting for its quote.
        $this->once('QA-B Bespoke To Quote', fn ($name) => $this->bespoke($name, 'pending'));
    }

    private function once(string $name, callable $build): void
    {
        if (! CustomOrder::withTrashed()->where('full_name', $name)->exists()) {
            $build($name);
        }
    }

    private function shopOrder(string $name, Product $product, int $price, string $method): CustomOrder
    {
        $order = CustomOrder::create([
            'full_name' => $name, 'email' => 'qa.b@example.test', 'phone' => self::PHONE, 'whatsapp_number' => self::PHONE,
            'address' => '1 QA Lane', 'city' => 'Nairobi', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT,
            'payment_method' => $method, 'price_quoted' => $price,
            'items_json' => [['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'price' => $price, 'quantity' => 1, 'options' => ['top_size' => 'M']]],
        ]);
        if ($product->batch_limit !== null) {
            $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => 1, 'status' => 'held', 'expires_at' => now()->addMinutes(15)]);
        }

        return $order;
    }

    private function bespoke(string $name, string $status): CustomOrder
    {
        return CustomOrder::create([
            'full_name' => $name, 'email' => 'qa.b@example.test', 'whatsapp_number' => self::PHONE,
            'type' => 'Hoodie & Jogger Set', 'status' => $status, 'fabric_preference' => 'French Terry',
            'items_json' => ['notes' => 'QA scenario', 'selected_color' => 'Midnight'],
        ]);
    }
}
