<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use App\Support\OrderStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function download($orderId)
    {
        // Archived orders keep their receipt: tracking still finds them and
        // mints this signed link (archiving is a Studio tidy-up).
        $order = CustomOrder::withTrashed()->findOrFail($orderId);
        
        // Ensure the order is paid
        if (! $order->isPaidUp()) {
            return back()->with('error', 'Receipts are only available for paid orders.');
        }

        // The same figures as the emailed receipts, as of the latest payment.
        $latest = $order->payments()->where('type', \App\Models\OrderPayment::PAYMENT)->latest('id')->first();
        $pdf = Pdf::loadView('receipts.luxury', \App\Mail\OrderReceiptMail::viewData($order, $latest));

        return $pdf->download('Serana-Receipt-' . ($order->cipher ?? $order->id) . '.pdf');
    }
}
