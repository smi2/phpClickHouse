<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\MissingBindingParamsException;
use ClickHouseDB\Transport\StreamRead;
use PHPUnit\Framework\TestCase;

use function fopen;

/**
 * Every {name:Type} placeholder in the SQL must have a matching param.
 *
 * @group NativeParamsTest
 */
final class NativeParamsValidationTest extends TestCase
{
    use WithClient;

    public function testSelectWithParamsThrowsWhenParamIsMissing(): void
    {
        $this->expectException(MissingBindingParamsException::class);

        $this->client->selectWithParams('SELECT {id:UInt32} as id', []);
    }

    public function testSelectWithParamsThrowsWhenParamHasDifferentName(): void
    {
        $this->expectException(MissingBindingParamsException::class);

        $this->client->selectWithParams('SELECT {id:UInt32} as id', ['other' => 1]);
    }

    public function testSelectWithParamsThrowsWhenSecondParamIsMissing(): void
    {
        $this->expectException(MissingBindingParamsException::class);

        $this->client->selectWithParams(
            'SELECT {a:UInt32} as a, {b:UInt32} as b',
            ['a' => 1]
        );
    }

    public function testSelectWithParamsThrowsWhenLastOfManyParamsIsMissing(): void
    {
        $this->expectException(MissingBindingParamsException::class);

        $this->client->selectWithParams(
            'SELECT {a:UInt32} as a, {b:String} as b, {c:Float64} as c',
            ['a' => 1, 'b' => 'x']
        );
    }

    /**
     * @dataProvider parameterizedTypeProvider
     */
    public function testSelectWithParamsThrowsWhenParamWithParameterizedTypeIsMissing(string $sql): void
    {
        $this->expectException(MissingBindingParamsException::class);

        $this->client->selectWithParams($sql, []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function parameterizedTypeProvider(): array
    {
        return [
            'Array(UInt32)' => ['SELECT {arr:Array(UInt32)} as arr'],
            'Nullable(String)' => ['SELECT {val:Nullable(String)} as val'],
            'DateTime64(3)' => ['SELECT {dt:DateTime64(3)} as dt'],
            'Array(Array(UInt32))' => ['SELECT {arr:Array(Array(UInt32))} as arr'],
        ];
    }

    public function testWriteWithParamsThrowsWhenParamIsMissing(): void
    {
        $this->expectException(MissingBindingParamsException::class);

        $this->client->writeWithParams(
            'INSERT INTO native_params_validation_test VALUES ({id:UInt32})',
            []
        );
    }

    public function testReadWithParamsThrowsWhenParamIsMissing(): void
    {
        $stream = fopen('php://memory', 'r+');

        $this->expectException(MissingBindingParamsException::class);

        $this->client->readWithParams(
            new StreamRead($stream),
            'SELECT {id:UInt32} as id FORMAT JSONEachRow',
            []
        );
    }

    public function testSelectWithParamsAcceptsRepeatedPlaceholderWithSingleParam(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {n:UInt32} + {n:UInt32} as result',
            ['n' => 5]
        );

        $this->assertEquals(10, $result->fetchOne('result'));
    }

    public function testSelectWithParamsAcceptsParamNameContainingParamPrefix(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {my_param_id:UInt32} as id',
            ['my_param_id' => 7]
        );

        $this->assertEquals(7, $result->fetchOne('id'));
    }
}
