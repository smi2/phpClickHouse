<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the deprecated partition helpers: the `:partition_id`
 * placeholder in dropPartition() must be substituted by Bindings.
 */
#[Group('DropPartitionTest')]
final class DropPartitionTest extends TestCase
{
    use WithClient;

    protected function setUp(): void
    {
        $this->client->write('DROP TABLE IF EXISTS drop_partition_test');
        $this->client->write(
            'CREATE TABLE drop_partition_test (d Date, v UInt8)
             ENGINE = MergeTree PARTITION BY toYYYYMM(d) ORDER BY v'
        );
        $this->client->write(
            "INSERT INTO drop_partition_test VALUES ('2026-01-05', 1), ('2026-02-05', 2), ('2026-02-06', 3)"
        );
    }

    protected function tearDown(): void
    {
        $this->client->write('DROP TABLE IF EXISTS drop_partition_test');
    }

    public function testDropPartitionRemovesOnlyThatPartition(): void
    {
        $this->client->dropPartition('drop_partition_test', '202601');

        $rows = $this->client->select('SELECT v FROM drop_partition_test ORDER BY v')->rows();

        $this->assertSame([2, 3], array_map('intval', array_column($rows, 'v')));
    }

    public function testTruncateTableDropsAllPartitions(): void
    {
        $this->client->truncateTable('drop_partition_test');

        $this->assertSame(0, (int) $this->client->select('SELECT count() AS c FROM drop_partition_test')->fetchOne('c'));
    }
}
