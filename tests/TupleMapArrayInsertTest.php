<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Type\MapType;
use ClickHouseDB\Type\TupleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tuples and maps inside arrays must be emitted as literals, not quoted strings.
 *
 * @link https://github.com/smi2/phpClickHouse/issues/283
 */
#[Group('TupleMapArrayInsertTest')]
final class TupleMapArrayInsertTest extends TestCase
{
    use WithClient;

    /** @return array<string, array{string, mixed, mixed}> */
    public static function columns(): array
    {
        return [
            'Tuple' => ['Tuple(UInt8, String)', TupleType::fromArray([1, "it's"]), [1, "it's"]],
            'Array(Tuple)' => [
                'Array(Tuple(UInt8, String))',
                [TupleType::fromArray([1, "it's"]), TupleType::fromArray([2, 'a,b'])],
                [[1, "it's"], [2, 'a,b']],
            ],
            'Array(Map)' => ['Array(Map(String, UInt8))', [MapType::fromArray(['k' => 1])], [['k' => 1]]],
        ];
    }

    #[DataProvider('columns')]
    public function testInsertRoundTrip(string $type, mixed $value, mixed $expected): void
    {
        $this->client->write('DROP TABLE IF EXISTS tuple_map_array');
        $this->client->write('CREATE TABLE tuple_map_array (t ' . $type . ') ENGINE = Memory');
        try {
            $this->client->insert('tuple_map_array', [[$value]], ['t']);

            $this->assertSame($expected, $this->client->select('SELECT t FROM tuple_map_array')->fetchOne('t'));
        } finally {
            $this->client->write('DROP TABLE IF EXISTS tuple_map_array');
        }
    }

    public function testInsertFormatKeepsLiteralsUnquoted(): void
    {
        $this->assertSame("(1,'a')", FormatLine::Insert([TupleType::fromArray([1, 'a'])]));
        $this->assertSame("[(1,'a'),(2,'b')]", FormatLine::Insert([[TupleType::fromArray([1, 'a']), TupleType::fromArray([2, 'b'])]]));
        $this->assertSame("[map('k',1)]", FormatLine::Insert([[MapType::fromArray(['k' => 1])]]));
    }

    public function testCsvFormatStillEnclosesTopLevelTuple(): void
    {
        // A bare tuple containing commas would split the CSV row into extra columns.
        $this->assertSame("\"(1,'a,b')\"", FormatLine::CSV([TupleType::fromArray([1, 'a,b'])]));
        $this->assertSame("\"[(1,'a,b')]\"", FormatLine::CSV([[TupleType::fromArray([1, 'a,b'])]]));
    }
}
