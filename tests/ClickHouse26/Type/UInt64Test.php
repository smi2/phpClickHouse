<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\ClickHouse26\Type;

use ClickHouseDB\Tests\WithClient;
use ClickHouseDB\Type\UInt64;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use function array_column;
use function implode;
use function sprintf;

/**
 * UInt64Test adapted for ClickHouse 26.x
 * CH 26.x returns numbers as native JSON types (int/float) instead of strings.
 *
 * @group ClickHouse26
 */
final class UInt64Test extends TestCase
{
    use WithClient;

    public function setUp(): void
    {
        $this->client->write('DROP TABLE IF EXISTS uint64_data');
        $this->client->write('
            CREATE TABLE IF NOT EXISTS uint64_data (
                date Date MATERIALIZED toDate(datetime),
                datetime DateTime,
                number UInt64
            )
            ENGINE = MergeTree
            PARTITION BY date
            ORDER BY (datetime);
        ');

        parent::setUp();
    }

    public function testWriteInsert(): void
    {
        $this->client->write(sprintf(
            'INSERT INTO uint64_data VALUES %s',
            implode(
                ',',
                [
                    sprintf('(now(), %s)', UInt64::fromString('0')),
                    sprintf('(now(), %s)', UInt64::fromString('1')),
                    sprintf('(now(), %s)', UInt64::fromString('18446744073709551615')),
                ]
            )
        ));

        $statement = $this->client->select('SELECT number FROM uint64_data ORDER BY number ASC');

        self::assertSame(3, $statement->count());

        $values = array_column($statement->rows(), 'number');
        // CH 26.x returns numbers as native types, not strings
        // Small values come as int, large UInt64 may come as float
        self::assertEquals(0, $values[0]);
        self::assertEquals(1, $values[1]);
        // UInt64 max overflows PHP float — check approximate value
        self::assertGreaterThan(1.8e19, $values[2]);
    }

    public function testInsert(): void
    {
        $now = new DateTimeImmutable();
        $this->client->insert(
            'uint64_data',
            [
                [$now, UInt64::fromString('0')],
                [$now, UInt64::fromString('1')],
                [$now, UInt64::fromString('18446744073709551615')],
            ]
        );

        $statement = $this->client->select('SELECT number FROM uint64_data ORDER BY number ASC');

        self::assertSame(3, $statement->count());

        $values = array_column($statement->rows(), 'number');
        self::assertEquals(0, $values[0]);
        self::assertEquals(1, $values[1]);
        self::assertGreaterThan(1.8e19, $values[2]);
    }

    /**
     * Test UInt64 values returned as strings using toString() in query.
     */
    public function testUInt64AsString(): void
    {
        $now = new DateTimeImmutable();
        $this->client->insert(
            'uint64_data',
            [
                [$now, UInt64::fromString('18446744073709551615')],
            ]
        );

        $statement = $this->client->select('SELECT toString(number) as num_str FROM uint64_data');

        self::assertSame(1, $statement->count());
        self::assertSame('18446744073709551615', $statement->fetchOne('num_str'));
    }
}
