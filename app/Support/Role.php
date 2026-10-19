<?php

namespace App\Support;

/**
 * Who someone is, and what each role may do (docs/design/BATCH-2-DESIGN.md §5).
 *
 * The matrix is fixed in code (owner decision D11) and enforced server-side as
 * Gates (AppServiceProvider). The UI only hides what the server would refuse.
 */
final class Role
{
    public const CLIENT = 'client';
    public const CASHIER = 'cashier';
    public const MANAGER = 'manager';
    /** The store owner (previously "admin"). */
    public const OWNER = 'owner';

    public const STAFF = [self::OWNER, self::MANAGER, self::CASHIER];

    /** ability => roles allowed. Phases add abilities here as they land. */
    public const MATRIX = [
        // Full Studio (cashiers get the POS only, once it exists).
        'studio.access' => [self::OWNER, self::MANAGER],
        // Studio → Settings → Payments and the M-Pesa connection test.
        'settings.payments' => [self::OWNER],
        // Record or confirm money received outside the STK flow.
        'money.record' => [self::OWNER, self::MANAGER],
        // Staff accounts and roles.
        'staff.manage' => [self::OWNER],
        // Order policies: deposit share, pay-link lifetime, deposit rule (design §5: owner only).
        'settings.policies' => [self::OWNER],
        // One bespoke order's deposit.
        'deposit.override' => [self::OWNER, self::MANAGER],
        // Start work without the deposit, or ship with a balance owed (N7), with a reason.
        'fulfilment.override' => [self::OWNER],
        // Reverse a booking that should not count, or confirm it genuine (Phase C).
        'ledger.reverse' => [self::OWNER],
    ];

    public static function allows(?string $role, string $ability): bool
    {
        return in_array($role, self::MATRIX[$ability] ?? [], true);
    }

    /** @return list<string> abilities this role holds (shared with the Studio UI). */
    public static function abilities(?string $role): array
    {
        return array_keys(array_filter(self::MATRIX, fn ($roles) => in_array($role, $roles, true)));
    }
}
