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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TypesTest extends TestCase
{
    public function testUInt64FromStringGetValue(): void
    {
        $uint = UInt64::fromString('12345');
        self::assertSame('12345', $uint->getValue());
    }

    public function testUInt64ToString(): void
    {
        $uint = UInt64::fromString('99999999999999999');
        self::assertSame('99999999999999999', (string) $uint);
    }

    public function testInt64FromStringGetValue(): void
    {
        $int = Int64::fromString('-99999');
        self::assertSame('-99999', $int->getValue());
    }

    public function testInt64ToString(): void
    {
        $int = Int64::fromString('-42');
        self::assertSame('-42', (string) $int);
    }

    public function testDecimalFromStringGetValue(): void
    {
        $decimal = Decimal::fromString('123.456');
        self::assertSame('123.456', $decimal->getValue());
    }

    public function testDecimalToString(): void
    {
        $decimal = Decimal::fromString('0.001');
        self::assertSame('0.001', (string) $decimal);
    }

    public function testUUIDFromStringGetValue(): void
    {
        $uuid = UUID::fromString('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->getValue());
    }

    public function testUUIDToString(): void
    {
        $uuid = UUID::fromString('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);
    }

    public function testIPv4FromStringGetValue(): void
    {
        $ip = IPv4::fromString('192.168.1.1');
        self::assertSame('192.168.1.1', $ip->getValue());
    }

    public function testIPv4ToString(): void
    {
        $ip = IPv4::fromString('10.0.0.1');
        self::assertSame('10.0.0.1', (string) $ip);
    }

    public function testIPv6FromStringGetValue(): void
    {
        $ip = IPv6::fromString('::1');
        self::assertSame('::1', $ip->getValue());
    }

    public function testIPv6ToString(): void
    {
        $ip = IPv6::fromString('2001:db8::1');
        self::assertSame('2001:db8::1', (string) $ip);
    }

    public function testDateTime64FromStringGetValue(): void
    {
        $dt = DateTime64::fromString('2024-01-15 10:30:00.123');
        self::assertSame('2024-01-15 10:30:00.123', $dt->getValue());
    }

    public function testDateTime64FromDateTimePrecision0(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 10:30:00.123456');
        $dt = DateTime64::fromDateTime($dateTime, 0);
        self::assertSame('2024-01-15 10:30:00', $dt->getValue());
    }

    public function testDateTime64FromDateTimePrecision3(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 10:30:00.123456');
        $dt = DateTime64::fromDateTime($dateTime, 3);
        self::assertSame('2024-01-15 10:30:00.123', $dt->getValue());
    }

    public function testDateTime64FromDateTimePrecision6(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 10:30:00.123456');
        $dt = DateTime64::fromDateTime($dateTime, 6);
        self::assertSame('2024-01-15 10:30:00.123456', $dt->getValue());
    }

    public function testDate32FromStringGetValue(): void
    {
        $date = Date32::fromString('2024-01-15');
        self::assertSame('2024-01-15', $date->getValue());
    }

    public function testDate32FromDateTimeFormatsAsYmd(): void
    {
        $dateTime = new DateTimeImmutable('2024-06-30 23:59:59');
        $date = Date32::fromDateTime($dateTime);
        self::assertSame('2024-06-30', $date->getValue());
    }

    public function testDate32ToString(): void
    {
        $date = Date32::fromString('1970-01-01');
        self::assertSame('1970-01-01', (string) $date);
    }

    public function testMapTypeFromArrayGetValue(): void
    {
        $map = MapType::fromArray(['key' => 'val']);
        self::assertSame("map('key','val')", $map->getValue());
    }

    public function testMapTypeWithIntegerValues(): void
    {
        $map = MapType::fromArray(['a' => 1, 'b' => 2]);
        self::assertSame("map('a',1,'b',2)", $map->getValue());
    }

    public function testMapTypeToString(): void
    {
        $map = MapType::fromArray(['x' => 'y']);
        self::assertSame("map('x','y')", (string) $map);
    }

    public function testTupleTypeFromArrayGetValue(): void
    {
        $tuple = TupleType::fromArray([1, 'abc']);
        self::assertSame("(1,'abc')", $tuple->getValue());
    }

    public function testTupleTypeWithNull(): void
    {
        $tuple = TupleType::fromArray([null, 'test']);
        self::assertSame("(NULL,'test')", $tuple->getValue());
    }

    public function testTupleTypeWithBool(): void
    {
        $tuple = TupleType::fromArray([true, false]);
        self::assertSame('(1,0)', $tuple->getValue());
    }

    public function testTupleTypeToString(): void
    {
        $tuple = TupleType::fromArray([42, 'hello']);
        self::assertSame("(42,'hello')", (string) $tuple);
    }
}
