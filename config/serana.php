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

];
