<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
