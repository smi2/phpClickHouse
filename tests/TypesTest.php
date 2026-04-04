<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Type\Date32;
use ClickHouseDB\Type\DateTime64;
use ClickHouseDB\Type\Decimal;
use ClickHouseDB\Type\Int64;
use ClickHouseDB\Type\IPv4;
use ClickHouseDB\Type\IPv6;
use ClickHouseDB\Type\MapType;
use ClickHouseDB\Type\TupleType;
use ClickHouseDB\Type\UInt64;
use ClickHouseDB\Type\UUID;
use PHPUnit\Framework\TestCase;

/**
 * @group TypesTest
 */
final class TypesTest extends TestCase
{
    use WithClient;

    public function testInt64(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_int64");
        $this->client->write("CREATE TABLE types_int64 (v Int64) ENGINE = Memory");

        $this->client->insert('types_int64', [
            [Int64::fromString('-9223372036854775808')],
            [Int64::fromString('9223372036854775807')],
        ], ['v']);

        $st = $this->client->select('SELECT count() as cnt FROM types_int64');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testUInt64(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_uint64");
        $this->client->write("CREATE TABLE types_uint64 (v UInt64) ENGINE = Memory");

        $this->client->insert('types_uint64', [
            [UInt64::fromString('0')],
            [UInt64::fromString('18446744073709551615')],
        ], ['v']);

        $st = $this->client->select('SELECT count() as cnt FROM types_uint64');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testDecimal(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_decimal");
        $this->client->write("CREATE TABLE types_decimal (v Decimal(18,4)) ENGINE = Memory");

        $this->client->insert('types_decimal', [
            [Decimal::fromString('12345.6789')],
            [Decimal::fromString('-99999.9999')],
        ], ['v']);

        $st = $this->client->select('SELECT count() as cnt FROM types_decimal');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testUUID(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_uuid");
        $this->client->write("CREATE TABLE types_uuid (id UUID) ENGINE = Memory");

        $uuid = '6d38d288-5b13-4714-b6e4-faa59ffd49d8';
        $this->client->insert('types_uuid', [
            [UUID::fromString($uuid)],
        ], ['id']);

        $st = $this->client->select('SELECT id FROM types_uuid');
        $this->assertEquals($uuid, $st->fetchOne('id'));
    }

    public function testIPv4(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_ipv4");
        $this->client->write("CREATE TABLE types_ipv4 (ip IPv4) ENGINE = Memory");

        $this->client->insert('types_ipv4', [
            [IPv4::fromString('192.168.1.1')],
            [IPv4::fromString('10.0.0.1')],
        ], ['ip']);

        $st = $this->client->select('SELECT count() as cnt FROM types_ipv4');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testIPv6(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_ipv6");
        $this->client->write("CREATE TABLE types_ipv6 (ip IPv6) ENGINE = Memory");

        $this->client->insert('types_ipv6', [
            [IPv6::fromString('::1')],
            [IPv6::fromString('2001:db8::1')],
        ], ['ip']);

        $st = $this->client->select('SELECT count() as cnt FROM types_ipv6');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testDateTime64(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_dt64");
        $this->client->write("CREATE TABLE types_dt64 (dt DateTime64(3)) ENGINE = Memory");

        $this->client->insert('types_dt64', [
            [DateTime64::fromString('2024-01-15 10:30:00.123')],
        ], ['dt']);

        $st = $this->client->select('SELECT count() as cnt FROM types_dt64');
        $this->assertEquals(1, $st->fetchOne('cnt'));
    }

    public function testDateTime64FromDateTime(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_dt64b");
        $this->client->write("CREATE TABLE types_dt64b (dt DateTime64(3)) ENGINE = Memory");

        $dt = new \DateTimeImmutable('2024-06-15 12:00:00.456789');
        $this->client->insert('types_dt64b', [
            [DateTime64::fromDateTime($dt, 3)],
        ], ['dt']);

        $st = $this->client->select('SELECT count() as cnt FROM types_dt64b');
        $this->assertEquals(1, $st->fetchOne('cnt'));
    }

    public function testDate32(): void
    {
        $this->client->write("DROP TABLE IF EXISTS types_date32");
        $this->client->write("CREATE TABLE types_date32 (d Date32) ENGINE = Memory");

        $this->client->insert('types_date32', [
            [Date32::fromString('2024-01-15')],
            [Date32::fromDateTime(new \DateTimeImmutable('2030-12-31'))],
        ], ['d']);

        $st = $this->client->select('SELECT count() as cnt FROM types_date32');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testNativeParamsWithUUID(): void
    {
        $uuid = '6d38d288-5b13-4714-b6e4-faa59ffd49d8';
        $st = $this->client->selectWithParams(
            'SELECT {id:UUID} as id',
            ['id' => UUID::fromString($uuid)]
        );
        $this->assertEquals($uuid, $st->fetchOne('id'));
    }

    public function testNativeParamsWithIPv4(): void
    {
        $st = $this->client->selectWithParams(
            'SELECT {ip:IPv4} as ip',
            ['ip' => IPv4::fromString('192.168.1.1')]
        );
        $this->assertStringContainsString('192.168.1.1', $st->fetchOne('ip'));
    }

    public function testNativeParamsWithDateTime64(): void
    {
        $st = $this->client->selectWithParams(
            "SELECT {dt:DateTime64(3)} as dt",
            ['dt' => DateTime64::fromString('2024-01-15 10:30:00.123')]
        );
        $this->assertStringContainsString('2024-01-15', $st->fetchOne('dt'));
    }

    public function testNativeParamsWithArray(): void
    {
        $st = $this->client->selectWithParams(
            "SELECT {arr:Array(UInt32)} as arr",
            ['arr' => [1, 2, 3]]
        );
        $row = $st->fetchOne();
        $this->assertIsArray($row['arr']);
        $this->assertCount(3, $row['arr']);
    }

    // Unit tests for type getValue()

    public function testInt64Value(): void
    {
        $v = Int64::fromString('42');
        $this->assertEquals('42', $v->getValue());
        $this->assertEquals('42', (string) $v);
    }

    public function testDecimalValue(): void
    {
        $v = Decimal::fromString('3.14');
        $this->assertEquals('3.14', $v->getValue());
    }

    public function testUUIDValue(): void
    {
        $v = UUID::fromString('abc-123');
        $this->assertEquals('abc-123', $v->getValue());
        $this->assertEquals('abc-123', (string) $v);
    }

    public function testIPv4Value(): void
    {
        $v = IPv4::fromString('1.2.3.4');
        $this->assertEquals('1.2.3.4', $v->getValue());
    }

    public function testIPv6Value(): void
    {
        $v = IPv6::fromString('::1');
        $this->assertEquals('::1', $v->getValue());
    }

    public function testDateTime64Value(): void
    {
        $v = DateTime64::fromString('2024-01-01 00:00:00.000');
        $this->assertEquals('2024-01-01 00:00:00.000', $v->getValue());
    }

    public function testDate32Value(): void
    {
        $v = Date32::fromString('2024-01-01');
        $this->assertEquals('2024-01-01', $v->getValue());
    }

    public function testMapTypeValue(): void
    {
        $v = MapType::fromArray(['key1' => 'val1', 'key2' => 'val2']);
        $this->assertStringContainsString('map(', $v->getValue());
    }

    public function testTupleTypeValue(): void
    {
        $v = TupleType::fromArray([1, 'hello', null]);
        $this->assertEquals("(1,'hello',NULL)", $v->getValue());
    }
}
