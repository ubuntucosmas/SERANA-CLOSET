<?php

namespace App\Http\Controllers;

use App\Models\CustomOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function download($orderId)
    {
        $order = CustomOrder::findOrFail($orderId);
        
        // Ensure the order is paid
        if (!$order->is_paid && $order->status !== 'Paid') {
            return back()->with('error', 'Receipts are only available for paid orders.');
        }

        $pdf = Pdf::loadView('receipts.luxury', [
            'order' => $order,
            'items' => $order->items_json ?: [],
            'date' => $order->updated_at->format('M d, Y H:i A')
        ]);

        return $pdf->download("Serana-Atelier-Receipt-{$order->id}.pdf");
    }
}
