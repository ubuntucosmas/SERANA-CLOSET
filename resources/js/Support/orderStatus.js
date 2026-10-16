// is_paid is the payment source of truth; status may also hold a production stage.
export function paymentStatus(order) {
    if (order.is_paid) return { label: 'Payment Confirmed', tone: 'success', next: 'Payment received. Follow your order for fulfilment updates.' };
    if (order.status === 'Payment Failed') return { label: 'Payment Failed', tone: 'error', next: 'Payment was not confirmed. Contact support if M-Pesa shows a charge.' };
    if (order.status === 'Amount Mismatch') return { label: 'Payment Needs Review', tone: 'error', next: 'Contact support with your order reference and M-Pesa receipt before paying again.' };
    if (order.status === 'Expired') return { label: 'Payment Window Expired', tone: 'warning', next: 'Contact support if you paid. Otherwise, return to the shop to place a new order.' };
    if (!['standard', 'drop'].includes(order.type)) {
        const quoted = Number(order.amount ?? order.price_quoted ?? 0) > 0;
        return quoted
            ? { label: 'Quote Ready', tone: 'pending', next: 'Your quote is ready. The studio will share how to pay on WhatsApp, and this page updates once payment is recorded.' }
            : { label: 'Quote Pending', tone: 'pending', next: 'The studio will review your brief and confirm pricing with you on WhatsApp.' };
    }
    return { label: 'Payment Pending', tone: 'pending', next: 'We have not confirmed payment. If you approved an M-Pesa prompt, wait for confirmation before paying again.' };
}
export function fulfilmentStatus(order) {
    const stages = { pending: 'Brief Received', designing: 'Designing', sewing: 'In Production', completed: 'Ready for Dispatch', shipped: 'Dispatched' };
    return stages[order.status] || (order.is_paid ? 'Awaiting Fulfilment' : 'Payment Pending');
}
