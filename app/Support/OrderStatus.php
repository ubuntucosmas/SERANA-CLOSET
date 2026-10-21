<?php

namespace App\Support;

/**
 * Canonical order status values.
 *
 * Two distinct lifecycles currently share the `status` column:
 *   - Payment states (shop checkout, server-authoritative — defined here).
 *   - Production states (custom-order workflow, driven by the studio dashboard:
 *     pending → designing → sewing → completed → shipped).
 *
 * Using these constants instead of bare strings keeps the payment-critical
 * comparisons spelling-safe and greppable. (Splitting payment vs production into
 * separate columns is a recommended future refactor.)
 */
final class OrderStatus
{
    public const PENDING_PAYMENT = 'Pending Payment';
    public const PAID            = 'Paid';
    public const PAYMENT_FAILED  = 'Payment Failed';
    public const AMOUNT_MISMATCH = 'Amount Mismatch';
    public const EXPIRED         = 'Expired';
    /** The studio cancelled an unpaid order (e.g. a WhatsApp order never paid). */
    public const CANCELLED       = 'Cancelled';

    /** Production lifecycle stages a custom order moves through in the studio. */
    public const PRODUCTION_STAGES = ['pending', 'designing', 'sewing', 'completed', 'shipped', 'collected'];

    /** Production stages that mean the studio is no longer actively working the order. */
    public const PRODUCTION_TERMINAL = ['completed', 'shipped', 'collected'];
}
