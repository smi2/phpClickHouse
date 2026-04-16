<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @group NativeParamsTest
 */
final class NativeParamsTest extends TestCase
{
    use WithClient;

    public function testSelectWithTypedParams(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {p1:UInt32} + {p2:UInt32} as result',
            ['p1' => 3, 'p2' => 4]
        );

        $this->assertEquals(7, $result->fetchOne('result'));
    }

    public function testSelectWithStringParam(): void
    {
        $result = $this->client->selectWithParams(
            "SELECT {name:String} as greeting",
            ['name' => 'Hello World']
        );

        $this->assertEquals('Hello World', $result->fetchOne('greeting'));
    }

    public function testSelectWithDateTimeParam(): void
    {
        $dt = new \DateTime('2024-01-15 10:30:00');
        $result = $this->client->selectWithParams(
            'SELECT {dt:DateTime} as dt_value',
            ['dt' => $dt]
        );

        $this->assertEquals('2024-01-15 10:30:00', $result->fetchOne('dt_value'));
    }

    public function testSelectWithMultipleParams(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {a:Int32} as a, {b:String} as b, {c:Float64} as c',
            ['a' => 42, 'b' => 'test', 'c' => 3.14]
        );

        $row = $result->fetchOne();
        $this->assertEquals(42, $row['a']);
        $this->assertEquals('test', $row['b']);
        $this->assertEqualsWithDelta(3.14, $row['c'], 0.001);
    }

    public function testWriteWithTypedParams(): void
    {
        $this->client->write("DROP TABLE IF EXISTS native_params_test");
        $this->client->write('CREATE TABLE IF NOT EXISTS native_params_test (id UInt32, name String) ENGINE = Memory');

        $this->client->writeWithParams(
            'INSERT INTO native_params_test VALUES ({id:UInt32}, {name:String})',
            ['id' => 1, 'name' => 'Alice']
        );

        $st = $this->client->select('SELECT * FROM native_params_test');
        $this->assertEquals(1, $st->count());
        $this->assertEquals('Alice', $st->fetchOne('name'));

        $this->client->write("DROP TABLE IF EXISTS native_params_test");
    }

    public function testSelectWithBoolParam(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {flag:Bool} as flag',
            ['flag' => true]
        );

        $this->assertEquals(1, $result->fetchOne('flag'));
    }

    public function testSelectWithNullableParam(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {val:Nullable(String)} as val',
            ['val' => null]
        );

        $this->assertNull($result->fetchOne('val'));
    }

    public function testSelectWithUInt32ArrayParam(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {arr:Array(UInt32)} as arr',
            ['arr' => [1, 2, 3]]
        );

        $this->assertEquals([1, 2, 3], $result->fetchOne('arr'));
    }

    public function testSelectWithStringArrayParam(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {arr:Array(String)} as arr',
            ['arr' => ['foo', 'bar', 'baz']]
        );

        $this->assertEquals(['foo', 'bar', 'baz'], $result->fetchOne('arr'));
    }

    public function testSelectWithEmptyArrayParam(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {arr:Array(UInt32)} as arr',
            ['arr' => []]
        );

        $this->assertEquals([], $result->fetchOne('arr'));
    }

    public function testSelectWithPerQuerySettings(): void
    {
        $result = $this->client->selectWithParams(
            'SELECT {n:UInt32} as n',
            ['n' => 1],
            ['max_execution_time' => 5]
        );

        $this->assertEquals(1, $result->fetchOne('n'));
    }
}
