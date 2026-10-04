<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class DatabaseConnectionConfigTest extends TestCase
{
    public function test_pgsql_connection_emulates_prepared_statements(): void
    {
        $options = config('database.connections.pgsql.options');

        $this->assertTrue(
            $options[PDO::ATTR_EMULATE_PREPARES] ?? false,
            'pgsql must emulate prepares: Neon\'s pooled endpoint aborts a transaction '
            .'after its first server-side prepared statement, which breaks checkout.',
        );
    }
}
