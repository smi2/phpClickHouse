<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Type;

use ClickHouseDB\Tests\WithClient;
use ClickHouseDB\Type\Date;
use ClickHouseDB\Type\Date32;
use ClickHouseDB\Type\DateTime;
use ClickHouseDB\Type\DateTime64;
use ClickHouseDB\Type\Enum16;
use ClickHouseDB\Type\Enum8;
use ClickHouseDB\Type\FixedString;
use ClickHouseDB\Type\IPv4;
use ClickHouseDB\Type\IPv6;
use ClickHouseDB\Type\StringType;
use ClickHouseDB\Type\Type;
use ClickHouseDB\Type\UUID;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** @group integration */
final class StringDateTypesIntegrationTest extends TestCase
{
    use WithClient;

    /** @dataProvider values */
    public function testInsertAndBindingsRoundTrip(string $type, Type $value, string $expected): void
    {
        $this->client->write('DROP TABLE IF EXISTS string_date_types');
        $this->client->write('CREATE TABLE string_date_types (value ' . $type . ') ENGINE = Memory');
        try {
            $this->client->insert('string_date_types', [[$value]], ['value']);
            $this->client->write('INSERT INTO string_date_types VALUES (:value)', ['value' => $value]);
            self::assertSame([['value' => $expected], ['value' => $expected]], $this->client->select(
                'SELECT value FROM string_date_types'
            )->rows());
        } finally {
            $this->client->write('DROP TABLE IF EXISTS string_date_types');
        }
    }

    /** @dataProvider values */
    public function testNativeParametersRoundTrip(string $type, Type $value, string $expected): void
    {
        self::assertSame($expected, $this->client->selectWithParams(
            'SELECT {value:' . $type . '} AS value',
            ['value' => $value]
        )->fetchOne('value'));
    }

    /** @return array<string, array{string, Type, string}> */
    public static function values(): array
    {
        return [
            'string' => ['String', StringType::fromString("it's\\a test"), "it's\\a test"],
            'empty string' => ['String', StringType::fromString(''), ''],
            'fixed string' => ['FixedString(1)', FixedString::fromString('a', 1), 'a'],
            'fixed bytes' => ['FixedString(2)', FixedString::fromString('é', 2), 'é'],
            'date' => ['Date', Date::fromString('2024-02-29'), '2024-02-29'],
            'date32' => ['Date32', Date32::fromString('1925-01-01'), '1925-01-01'],
            'datetime' => ["DateTime('UTC')", DateTime::fromString('2024-02-29 23:45:12'), '2024-02-29 23:45:12'],
            'datetime64' => ["DateTime64(9, 'UTC')", DateTime64::fromString('2024-02-29 23:45:12.123456789'), '2024-02-29 23:45:12.123456789'],
            'datetime64 timezone' => [
                "DateTime64(3, 'Europe/Amsterdam')",
                DateTime64::fromDateTime(new DateTimeImmutable('2024-02-29 23:45:12.123456+00:00'), 3, 'Europe/Amsterdam'),
                '2024-03-01 00:45:12.123',
            ],
            'uuid' => ['UUID', UUID::fromString('550e8400-e29b-41d4-a716-446655440000'), '550e8400-e29b-41d4-a716-446655440000'],
            'ipv4' => ['IPv4', IPv4::fromString('192.168.1.1'), '192.168.1.1'],
            'ipv6' => ['IPv6', IPv6::fromString('2001:db8::1'), '2001:db8::1'],
            'enum8' => ["Enum8('low' = -128, 'high' = 127)", Enum8::fromString('low'), 'low'],
            'enum16' => ["Enum16('low' = -32768, 'high' = 32767)", Enum16::fromString('high'), 'high'],
        ];
    }
}
