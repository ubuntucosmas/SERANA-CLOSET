<?php

namespace App\Console\Commands;

use App\Mail\OrderReceiptMail;
use App\Models\CustomOrder;
use App\Support\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Verifies mail delivery end to end after configuring SMTP.
 *
 * Run `php artisan serana:mail-test you@example.com` once real MAIL_* creds are
 * in .env. It sends the actual paid-order receipt (using your latest paid order,
 * or a realistic sample) so you confirm both connectivity AND that the receipt
 * renders + attaches — not just that "a mail went out".
 */
class TestMailConfiguration extends Command
{
    protected $signature = 'serana:mail-test {email : Where to send the test receipt}';
    protected $description = 'Send a test order receipt to verify SMTP configuration';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not a valid email address.");
            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $this->line("Mail driver: <comment>{$mailer}</comment>");
        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is still "log" — this writes to storage/logs, it does NOT send.');
            $this->warn('Set MAIL_MAILER=smtp and your MAIL_* / STORE_NOTIFICATION_EMAIL creds in .env first.');
        }

        $order = CustomOrder::where('is_paid', true)->latest()->first()
            ?? $this->sampleOrder();

        $this->line("Sending receipt for reference <info>{$order->cipher}</info> to <info>{$email}</info>…");

        try {
            Mail::to($email)->send(new OrderReceiptMail($order));
        } catch (\Throwable $e) {
            $this->error('Send FAILED: ' . $e->getMessage());
            $this->line('Check MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD / MAIL_SCHEME.');
            return self::FAILURE;
        }

        $this->info('✓ Mail sent without error.');
        $this->line($mailer === 'log'
            ? 'It was written to storage/logs/laravel.log (log driver).'
            : "Check {$email} (and spam). If it arrived, your store mail is live.");

        return self::SUCCESS;
    }

    /** A realistic, unsaved order so the test works even before any real sale. */
    private function sampleOrder(): CustomOrder
    {
        return new CustomOrder([
            'cipher'       => 'SRN-SAMPLE00-' . date('Y'),
            'full_name'    => 'Test Customer',
            'email'        => 'test@example.com',
            'phone'        => '254700000000',
            'type'         => 'standard',
            'status'       => OrderStatus::PAID,
            'is_paid'      => true,
            'price_quoted' => 4500,
            'mpesa_receipt_number' => 'TEST123456',
            'items_json'   => [['name' => 'Silk Dress', 'quantity' => 1]],
        ]);
    }
}
