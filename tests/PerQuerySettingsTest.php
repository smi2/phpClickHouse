<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @group PerQuerySettingsTest
 */
final class PerQuerySettingsTest extends TestCase
{
    use WithClient;

    public function testSelectWithPerQuerySettings(): void
    {
        $result = $this->client->select(
            'SELECT 1 as n',
            [],
            null,
            null,
            ['max_execution_time' => 5]
        );

        $this->assertEquals(1, $result->fetchOne('n'));
        // Global setting should be unchanged
        $this->assertEquals(20, $this->client->settings()->getSetting('max_execution_time'));
    }

    public function testWriteWithPerQuerySettings(): void
    {
        $this->client->write("DROP TABLE IF EXISTS pqs_test");
        $this->client->write(
            'CREATE TABLE IF NOT EXISTS pqs_test (id UInt32) ENGINE = Memory',
            [],
            true,
            ['max_execution_time' => 5]
        );

        $this->client->insert('pqs_test', [[1], [2]], ['id']);
        $st = $this->client->select('SELECT count() as cnt FROM pqs_test');
        $this->assertEquals(2, $st->fetchOne('cnt'));

        $this->client->write("DROP TABLE IF EXISTS pqs_test");
    }

    public function testSelectAsyncWithPerQuerySettings(): void
    {
        $state1 = $this->client->selectAsync('SELECT 1 as n', [], null, null, ['max_execution_time' => 5]);
        $state2 = $this->client->selectAsync('SELECT 2 as n', [], null, null, ['max_execution_time' => 10]);

        $this->client->executeAsync();

        $this->assertEquals(1, $state1->fetchOne('n'));
        $this->assertEquals(2, $state2->fetchOne('n'));
    }
}
