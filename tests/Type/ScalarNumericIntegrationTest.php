<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Type;

use ClickHouseDB\Tests\WithClient;
use ClickHouseDB\Type\Boolean;
use ClickHouseDB\Type\Decimal128;
use ClickHouseDB\Type\Decimal256;
use ClickHouseDB\Type\Decimal32;
use ClickHouseDB\Type\Decimal64;
use ClickHouseDB\Type\Float32;
use ClickHouseDB\Type\Float64;
use ClickHouseDB\Type\Int128;
use ClickHouseDB\Type\Int16;
use ClickHouseDB\Type\Int256;
use ClickHouseDB\Type\Int32;
use ClickHouseDB\Type\Int64;
use ClickHouseDB\Type\Int8;
use ClickHouseDB\Type\UInt128;
use ClickHouseDB\Type\UInt16;
use ClickHouseDB\Type\UInt256;
use ClickHouseDB\Type\UInt32;
use ClickHouseDB\Type\UInt64;
use ClickHouseDB\Type\UInt8;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_replace;
use function strtolower;

/**
 * @group integration
 */
final class ScalarNumericIntegrationTest extends TestCase
{
    use WithClient;

    /**
     * @dataProvider scalarTypes
     */
    public function testInsertSelectAndComparison(
        string $typeName,
        string $className,
        string $lower,
        string $higher
    ): void {
        $table = 'scalar_numeric_' . strtolower(str_replace(['(', ')'], '', $typeName));
        $this->client->write(sprintf('DROP TABLE IF EXISTS %s', $table));
        $this->client->write(sprintf(
            'CREATE TABLE %s (value %s) ENGINE = Memory',
            $table,
            $typeName
        ));

        $this->client->insert($table, [
            [$className::fromString($lower)],
            [$className::fromString($higher)],
        ]);

        // CAST the literal to the column type: Decimal vs Float comparison is
        // unsupported on CH 21, and a bare Float64 literal 1.1 differs from the
        // stored Float32 representation.
        $statement = $this->client->select(sprintf(
            'SELECT value FROM %s WHERE value > CAST(%s AS %s)',
            $table,
            $className::fromString($lower),
            $typeName
        ));

        self::assertSame(1, $statement->count());
    }

    /**
     * @return array<string, array{typeName: string, className: class-string, lower: string, higher: string}>
     */
    public static function scalarTypes(): array
    {
        return [
            'Int8' => ['typeName' => 'Int8', 'className' => Int8::class, 'lower' => '-2', 'higher' => '1'],
            'Int16' => ['typeName' => 'Int16', 'className' => Int16::class, 'lower' => '-2', 'higher' => '1'],
            'Int32' => ['typeName' => 'Int32', 'className' => Int32::class, 'lower' => '-2', 'higher' => '1'],
            'Int64' => ['typeName' => 'Int64', 'className' => Int64::class, 'lower' => '-2', 'higher' => '1'],
            'Int128' => ['typeName' => 'Int128', 'className' => Int128::class, 'lower' => '-2', 'higher' => '1'],
            'Int256' => ['typeName' => 'Int256', 'className' => Int256::class, 'lower' => '-2', 'higher' => '1'],
            'UInt8' => ['typeName' => 'UInt8', 'className' => UInt8::class, 'lower' => '1', 'higher' => '2'],
            'UInt16' => ['typeName' => 'UInt16', 'className' => UInt16::class, 'lower' => '1', 'higher' => '2'],
            'UInt32' => ['typeName' => 'UInt32', 'className' => UInt32::class, 'lower' => '1', 'higher' => '2'],
            'UInt64' => ['typeName' => 'UInt64', 'className' => UInt64::class, 'lower' => '1', 'higher' => '2'],
            'UInt128' => ['typeName' => 'UInt128', 'className' => UInt128::class, 'lower' => '1', 'higher' => '2'],
            'UInt256' => ['typeName' => 'UInt256', 'className' => UInt256::class, 'lower' => '1', 'higher' => '2'],
            'Float32' => ['typeName' => 'Float32', 'className' => Float32::class, 'lower' => '1.1', 'higher' => '2.2'],
            'Float64' => ['typeName' => 'Float64', 'className' => Float64::class, 'lower' => '1.1', 'higher' => '2.2'],
            'Decimal32' => ['typeName' => 'Decimal32(2)', 'className' => Decimal32::class, 'lower' => '1.1', 'higher' => '2.2'],
            'Decimal64' => ['typeName' => 'Decimal64(2)', 'className' => Decimal64::class, 'lower' => '1.1', 'higher' => '2.2'],
            'Decimal128' => ['typeName' => 'Decimal128(2)', 'className' => Decimal128::class, 'lower' => '1.1', 'higher' => '2.2'],
            'Decimal256' => ['typeName' => 'Decimal256(2)', 'className' => Decimal256::class, 'lower' => '1.1', 'higher' => '2.2'],
            'Bool' => ['typeName' => 'Bool', 'className' => Boolean::class, 'lower' => '0', 'higher' => '1'],
        ];
    }
}
