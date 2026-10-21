<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ledger reads (batch 2, design §2.3 step A4)
    |--------------------------------------------------------------------------
    |
    | Off: the app reads the legacy order columns (status, type, is_paid,
    | price_quoted). On: it reads the split columns and the ledger instead.
    | Both are written on every change (dual-write) and checked after every
    | test, so the answers are identical; this switch is the rollback path for
    | the cutover. Turn it on only after `php artisan serana:ledger:verify`
    | reports 0 differences on the production data.
    |
    */

    'ledger_reads' => (bool) env('LEDGER_READS', false),

    /*
    |--------------------------------------------------------------------------
    | Stock per size (Phase D1, design §13.5)
    |--------------------------------------------------------------------------
    |
    | "setting" (default): the Studio decides. Off until the owner's stock take
    | is confirmed (serana:stock:cutover), which turns it on; turning it off
    | again is the rollback. "on" / "off" force it (CI runs the suite both
    | ways; "off" is the emergency brake without a deploy of code).
    |
    */

    'stock_v2' => env('STOCK_V2', 'setting'),

];
