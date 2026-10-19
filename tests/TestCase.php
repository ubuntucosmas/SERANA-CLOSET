<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Batch 2 dual-run safety net (design §9): after every passing test, every
     * order in the database must agree with the ledger and the split columns,
     * whatever the test did. A test that sets up drift on purpose opts out.
     *
     * @var list<string>
     */
    protected array $ledgerChecks = [\App\Services\LedgerVerifier::MAPPING, \App\Services\LedgerVerifier::MONEY, \App\Services\LedgerVerifier::READS];

    protected function tearDown(): void
    {
        if ($this->ledgerChecks !== [] && isset($this->app) && $this->status()->isSuccess()) {
            $differences = [];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('order_payments')) {
                    $differences = $this->app->make(\App\Services\LedgerVerifier::class)->differences($this->ledgerChecks);
                }
            } catch (\Illuminate\Database\QueryException) {
                // No database in this test.
            }
        }

        // Always clean up first (database rollback, app), then report drift.
        parent::tearDown();

        if (($differences ?? []) !== []) {
            $this->fail("Orders drifted from the ledger/split columns after this test:\n" . json_encode($differences, JSON_PRETTY_PRINT));
        }
    }

    /**
     * The suite runs on SQLite by default (phpunit.xml) and on MariaDB when
     * DB_CONNECTION/DB_DATABASE are set in the environment (CI, concurrency
     * tests). Tests wipe the database, so refuse any real database whose
     * name does not say it is for testing, before anything is touched.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");
        if ($app['config']->get("database.connections.{$connection}.driver") !== 'sqlite' && ! str_contains($database, 'test')) {
            throw new \RuntimeException("Refusing to run tests against database \"{$database}\": use a database whose name contains \"test\".");
        }

        return $app;
    }
}
