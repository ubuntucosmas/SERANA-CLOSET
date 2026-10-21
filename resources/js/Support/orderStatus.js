// is_paid is the payment source of truth; status may also hold a production stage.
export function paymentStatus(order) {
    if (order.is_paid) return { label: 'Payment Confirmed', tone: 'success', next: 'Payment received. Follow your order for fulfilment updates.' };
    if (order.status === 'Payment Failed') return { label: 'Payment Failed', tone: 'error', next: 'Payment was not confirmed. Contact support if M-Pesa shows a charge.' };
    if (order.status === 'Amount Mismatch') return { label: 'Payment Needs Review', tone: 'error', next: 'Contact support with your order reference and M-Pesa receipt before paying again.' };
    if (order.status === 'Cancelled') return { label: 'Order Cancelled', tone: 'warning', next: 'This order was cancelled. Check any recorded payments or refunds with the Studio before placing another order.' };
    if (order.status === 'Expired') return { label: 'Payment Window Expired', tone: 'warning', next: 'Contact support if you paid. Otherwise, return to the shop to place a new order.' };
    if (!['standard', 'drop'].includes(order.type)) {
        // Part-paid bespoke work: a deposit is in, the balance comes before dispatch.
        if (Number(order.amount_paid ?? 0) > 0 && Number(order.balance_due ?? 0) > 0) {
            const balance = Number(order.balance_due).toLocaleString('en-US');
            return { label: 'Deposit Paid', tone: 'pending', next: `Deposit received. The balance of KSh ${balance} is due before your order leaves the studio.` };
        }
        const quoted = Number(order.amount ?? order.price_quoted ?? 0) > 0;
        return quoted
            ? { label: 'Quote Ready', tone: 'pending', next: 'Your quote is ready. The studio will share how to pay on WhatsApp, and this page updates once payment is recorded.' }
            : { label: 'Quote Pending', tone: 'pending', next: 'The studio will review your brief and confirm pricing with you on WhatsApp.' };
    }
    if (order.payment_method === 'whatsapp') return { label: 'Awaiting Payment', tone: 'pending', next: 'Send your order on WhatsApp and pay as the studio instructs. This page updates as soon as your payment is confirmed.' };
    return { label: 'Payment Pending', tone: 'pending', next: 'We have not confirmed payment. If you approved an M-Pesa prompt, wait for confirmation before paying again.' };
}
export function fulfilmentStatus(order) {
    if (order.status === 'completed' && (order.fulfilment_method === 'pickup' || order.handover?.method === 'pickup')) return 'Ready for Collection';
    const stages = { pending: 'Brief Received', designing: 'Designing', sewing: 'In Production', completed: 'Ready for Dispatch', shipped: 'Dispatched', collected: 'Collected' };
    return stages[order.status] || (order.is_paid ? 'Awaiting Fulfilment' : 'Payment Pending');
}
