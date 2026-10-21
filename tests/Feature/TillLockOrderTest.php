<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Phase E (design §15.2, §13.3): the till session is the last step of the
 * global lock order (stock levels → holds → order row → ledger → till).
 * TillService is the only writer of cash movements and the only place a
 * till session is locked; a caller locks the session through it after the
 * money is booked, and takes no other lock after that.
 */
class TillLockOrderTest extends TestCase
{
    /** Anything that takes a lock earlier in the global order. */
    private const EARLIER = '/lockForMoney\(|recordPayment\(|recordRefund\(|lockLevels\(|lockForOrder\(|->allocate\(|lockStock\(|lockForUpdate\(|sharedLock\(/';

    /** @return iterable<string, array{0: string, 1: string}> path => contents, outside TillService */
    private function appFiles(): iterable
    {
        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            yield str_replace('\\', '/', $file->getRelativePathname()) => $file->getContents();
        }
    }

    public function test_only_till_service_writes_cash_or_locks_a_till_session(): void
    {
        $violations = [];
        foreach ($this->appFiles() as $path => $contents) {
            if ($path === 'Services/TillService.php') {
                continue;
            }
            foreach (preg_split('/\R/', $contents) as $n => $line) {
                if (preg_match('/^\s*(\*|\/\/)/', $line)) {
                    continue;
                }
                if (preg_match('/CashMovement::(create|insert|upsert|query)|cash_movements\'\)->(insert|update|delete)|new CashMovement/', $line)
                    || (preg_match('/TillSession::/', $line) && preg_match('/lockForUpdate\(|sharedLock\(/', $line))) {
                    $violations[] = "{$path}:" . ($n + 1) . ': ' . trim($line);
                }
            }
        }

        $this->assertSame([], $violations, 'Write cash and lock till sessions through TillService (cash, lock).');
    }

    public function test_the_till_lock_is_taken_last_and_before_any_cash_is_written(): void
    {
        $violations = [];
        foreach ($this->appFiles() as $path => $contents) {
            if ($path === 'Services/TillService.php' || ! str_contains($contents, '->tills->')) {
                continue;
            }
            // Crudely, one chunk per method: enough for code that keeps one transaction per method.
            foreach (preg_split('/\n\s*(?:public|private|protected) function /', $contents) as $method) {
                $name = strtok($method, '(');
                $lock = strpos($method, '->tills->lock(');
                $cash = strpos($method, '->tills->cash(');
                if ($cash !== false && ($lock === false || $lock > $cash)) {
                    $violations[] = "{$path} {$name}(): writes cash without locking the till session first";
                }
                if ($lock !== false && preg_match(self::EARLIER, substr($method, $lock + strlen('->tills->lock(')), $m)) {
                    $violations[] = "{$path} {$name}(): takes {$m[0]} after the till lock";
                }
            }
        }

        $this->assertSame([], $violations, 'The till session is the last lock (design §13.3).');
    }
}
