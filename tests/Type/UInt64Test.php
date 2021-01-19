<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Type;

use ClickHouseDB\Tests\WithClient;
use ClickHouseDB\Type\UInt64;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use function array_column;
use function implode;
use function sprintf;

/**
 * @group integration
 */
final class UInt64Test extends TestCase
{
    use WithClient;

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testWriteInsert()
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
        self::assertSame(['0', '1', '18446744073709551615'], array_column($statement->rows(), 'number'));
    }

    /**
     * @return void
     */
    public function testInsert()
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
        self::assertSame(['0', '1', '18446744073709551615'], array_column($statement->rows(), 'number'));
    }
}
