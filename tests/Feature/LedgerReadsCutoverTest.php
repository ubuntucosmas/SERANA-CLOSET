<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Batch 2 Phase A4 (design §2.3, §9): app code reads an order's state only
 * through ReadsOrderState, so LEDGER_READS switches the whole app at once and
 * the legacy columns can later be dropped (A5). Writes of the legacy columns
 * (the dual-write) are allowed; reads are not.
 */
class LedgerReadsCutoverTest extends TestCase
{
    /** Where the legacy columns may still be read: the adapter, the mapping, backfill and verify, and settlement's dual-write. */
    private const ALLOWED = [
        'Models/Concerns/ReadsOrderState.php',
        'Support/LegacyOrderMap.php',
        'Services/LedgerBackfill.php',
        'Services/LedgerVerifier.php',
        'Services/OrderSettlementService.php',
        'Console/Commands/LedgerVerifyCommand.php',
    ];

    /** Models whose own status/type columns are not an order's. */
    private const OWN_COLUMNS = ['Models/PaymentAttempt.php', 'Models/StockReservation.php', 'Support/Actor.php'];

    private const READS = [
        '/->is_paid\b/',
        '/->type\s*(===|!==|==|!=)/',
        '/in_array\(\$\w+->(type|status)\b/',
        '/->status\s*(===|!==|==|!=)\s*(OrderStatus::|\'(pending|designing|sewing|completed|shipped)\')/',
        '/(where|whereIn|whereNotIn|orWhere)\(\s*\'(is_paid|type|status|price_quoted)\'/',
        '/->price_quoted\b/',
        '/sum\(\'price_quoted\'\)/',
    ];

    public function test_order_state_is_read_only_through_the_adapter(): void
    {
        $found = [];
        foreach ([...File::allFiles(app_path()), ...File::allFiles(base_path('routes'))] as $file) {
            $path = str_replace([app_path() . '/', base_path() . '/'], '', $file->getPathname());
            if (in_array($path, self::ALLOWED, true) || in_array($path, self::OWN_COLUMNS, true)) {
                continue;
            }
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (preg_match('/^\s*(\*|\/\/)/', $line)) {
                    continue; // comments
                }
                foreach (self::READS as $pattern) {
                    if (preg_match($pattern, $line) && ! $this->isLegacyWriteOrDropOrAttempt($line)) {
                        $found[] = $path . ':' . ($number + 1) . '  ' . trim($line);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $found, "Legacy order columns read directly:\n" . implode("\n", $found));
    }

    /** A write of a legacy key (the dual-write), or a Drop, stock hold or payment attempt column. */
    private function isLegacyWriteOrDropOrAttempt(string $line): bool
    {
        return (bool) preg_match("/('(status|type|is_paid|price_quoted)'\\s*=>\\s*(?!\\$\\w+->)|Drop::|PaymentAttempt::|\\\$drop->|\\\$attempt->status|reservation|'held'|'released'|'committed')/i", $line);
    }
}
