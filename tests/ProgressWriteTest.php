<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @group ProgressWriteTest
 * @link https://github.com/smi2/phpClickHouse/issues/191
 */
final class ProgressWriteTest extends TestCase
{
    use WithClient;

    /**
     * Test that progressFunction works with write/insert operations.
     */
    public function testProgressFunctionOnInsert(): void
    {
        $this->client->write("DROP TABLE IF EXISTS progress_write_test");
        $this->client->write("CREATE TABLE progress_write_test (id UInt32) ENGINE = Memory");

        $progressData = [];
        $this->client->progressFunction(function ($data) use (&$progressData) {
            $progressData[] = $data;
        });

        // Insert enough data to trigger progress callbacks
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = [$i];
        }
        $this->client->insert('progress_write_test', $rows, ['id']);

        $st = $this->client->select('SELECT count() as cnt FROM progress_write_test');
        $this->assertEquals(1000, $st->fetchOne('cnt'));

        // wait_end_of_query setting should be enabled
        $this->assertEquals(1, $this->client->settings()->getSetting('wait_end_of_query'));
        $this->assertEquals(1, $this->client->settings()->getSetting('send_progress_in_http_headers'));

        $this->client->write("DROP TABLE IF EXISTS progress_write_test");
    }
}
