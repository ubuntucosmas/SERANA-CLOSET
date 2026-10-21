import { test } from 'node:test';
import assert from 'node:assert/strict';
import { paymentStatus } from '../../resources/js/Support/orderStatus.js';

const bespoke = (o = {}) => ({ type: 'Gown', status: 'designing', is_paid: false, price_quoted: 10000, ...o });

test('a bespoke order with a deposit paid reads "Deposit Paid", not "Quote Ready"', () => {
    const s = paymentStatus(bespoke({ amount_paid: 4000, balance_due: 6000 }));
    assert.equal(s.label, 'Deposit Paid');
    assert.match(s.next, /6,000/);
});

test('a quoted bespoke order with nothing paid is still "Quote Ready"', () => {
    assert.equal(paymentStatus(bespoke({ amount_paid: 0, balance_due: 10000 })).label, 'Quote Ready');
    assert.equal(paymentStatus(bespoke()).label, 'Quote Ready', 'payloads without ledger fields keep working');
});

test('a fully paid order is "Payment Confirmed" whatever was paid before', () => {
    assert.equal(paymentStatus(bespoke({ is_paid: true, amount_paid: 10000, balance_due: 0 })).label, 'Payment Confirmed');
});

test('an order handed over at the till reads Collected', async () => {
    const { fulfilmentStatus } = await import('../../resources/js/Support/orderStatus.js');
    assert.equal(fulfilmentStatus({ status: 'collected', is_paid: true }), 'Collected');
});


test('a pickup order is ready for pickup only when the Studio says so, never "ready for dispatch"', async () => {
    const { fulfilmentStatus } = await import('../../resources/js/Support/orderStatus.js');
    // Phase D2: ready for pickup is its own stage; a finished pickup order isn't waiting for a courier.
    assert.equal(fulfilmentStatus({ status: 'ready_for_pickup', fulfilment_method: 'pickup' }), 'Ready for Pickup');
    assert.equal(fulfilmentStatus({ status: 'completed', handover: { method: 'pickup' } }), 'Finished');
    assert.equal(fulfilmentStatus({ status: 'completed', fulfilment_method: 'delivery' }), 'Ready for Dispatch');
});
