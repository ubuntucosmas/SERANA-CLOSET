<?php

namespace App\Support;

/** Audit and stock records in the words staff use (Studio → Activity history). */
final class ActivityLabels
{
    private const ACTIONS = [
        'delivery.option_added' => 'Added a delivery option', 'delivery.option_updated' => 'Changed a delivery option',
        'fulfilment.override' => 'Overrode a fulfilment rule', 'handover.shop_updated' => 'Changed the pickup shop',
        'order.archived' => 'Archived an order', 'order.cancelled' => 'Cancelled an order', 'order.delivery_set' => 'Set the order’s delivery',
        'order.deposit_set' => 'Set the order’s deposit', 'order.expired_part_paid' => 'Expired a part-paid order (refund owed)',
        'order.purged' => 'Permanently deleted an order', 'order.quote_lowered' => 'Lowered a quote below what was paid',
        'order.resolved' => 'Resolved a payment or stock problem', 'order.restored' => 'Restored an archived order', 'order.updated' => 'Updated an order',
        'payment.confirmed_genuine' => 'Confirmed a flagged payment as genuine', 'payment.recorded' => 'Recorded a payment', 'payment.unmatched' => 'Received an M-Pesa payment that matches no order',
        'payment.reversed' => 'Reversed a payment booked in error', 'refund.recorded' => 'Recorded a refund',
        'pos.collected' => 'Handed over a pickup order', 'pos.intake' => 'Took a custom order at the Sales Desk',
        'pos.late_entry' => 'Entered a paper slip sale', 'pos.mpesa_prompt' => 'Sent an M-Pesa prompt at the Sales Desk',
        'pos.pin_lockout' => 'Locked the PIN pad after wrong PINs', 'pos.return' => 'Took a return at the Sales Desk',
        'pos.sale_started' => 'Started a Sales Desk sale', 'pos.sale_voided' => 'Voided an unpaid sale',
        'product.archived' => 'Archived a product', 'product.created' => 'Created a product', 'product.purged' => 'Permanently deleted a product',
        'product.restored' => 'Restored a product', 'product.updated' => 'Edited a product',
        'settings.order_policy.updated' => 'Changed the order policy', 'settings.payments.updated' => 'Changed payment settings',
        'settings.shop_policy.updated' => 'Changed the Sales Desk rules', 'settings.store.updated' => 'Changed store details',
        'settings.custom_order_options.updated' => 'Changed custom order options',
        'staff.added' => 'Added a staff member', 'staff.details_changed' => 'Changed a staff member’s details',
        'staff.password_changed' => 'Changed their own password', 'staff.password_recovered' => 'Reset a password by email link',
        'staff.password_reset' => 'Issued a temporary password', 'staff.pin_set' => 'Changed a Sales Desk PIN',
        'staff.reactivated' => 'Reactivated a staff account', 'staff.role_changed' => 'Changed a staff role', 'staff.saved' => 'Saved a staff account',
        'staff.sessions_ended' => 'Ended a staff member’s sessions', 'staff.suspended' => 'Suspended a staff account',
        'stock.counted' => 'Counted stock', 'stock.cutover' => 'Switched stock per size on', 'stock.moved' => 'Changed stock',
        'stock.rollback' => 'Switched stock per size off', 'stock.sizes' => 'Changed the sizes on offer',
        'stock.slip_shortfall' => 'Recorded a paper-slip stock shortfall', 'stock.taken_for_slip' => 'Took stock for a paper slip',
        'stock.tracking' => 'Changed stock counting (counted or made to order)',
        'till.opened' => 'Opened a register', 'till.closed' => 'Closed a register', 'till.payout' => 'Paid cash out of the register',
        'till.pickup' => 'Removed cash from the register for safekeeping',
    ];

    private const MOVEMENTS = [
        'receive' => 'Received stock', 'sale' => 'Sold', 'return' => 'Returned to stock', 'count_correction' => 'Corrected by a count',
        'damage' => 'Wrote off', 'transfer_out' => 'Moved out to another location', 'transfer_in' => 'Moved in from another location',
    ];

    public static function action(string $action, bool $movement = false): string
    {
        $known = $movement ? (self::MOVEMENTS[$action] ?? null) : (self::ACTIONS[$action] ?? null);

        return $known ?? ucfirst(str_replace(['.', '_', 'till', 'pos'], [' ', ' ', 'register', 'Sales Desk'], $action));
    }

    /** Where it happened, in staff words. */
    public static function where(string $action): string
    {
        return str_starts_with($action, 'pos.') || str_starts_with($action, 'till.') ? 'Sales Desk' : 'Studio';
    }
}
