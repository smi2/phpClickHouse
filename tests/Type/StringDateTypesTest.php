<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Type;

use ClickHouseDB\Query\Degeneration\Bindings;
use ClickHouseDB\Quote\ValueFormatter;
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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Stringable;

use function array_map;

final class StringDateTypesTest extends TestCase
{
    /** @dataProvider stringTypes */
    public function testValuesAreQuotedInBindings(string $class): void
    {
        $value = $class::fromString("a'b\\c\0");
        self::assertInstanceOf(Type::class, $value);
        self::assertInstanceOf(Stringable::class, $value);
        self::assertSame("a'b\\c\0", $value->getValue());
        self::assertSame("a'b\\c\0", (string) $value);
        self::assertSame("a'b\\c\0", ValueFormatter::formatValue($value, false));
        self::assertSame("'a\\'b\\\\c\\0'", ValueFormatter::formatValue($value));
        $bindings = new Bindings();
        $bindings->bindParam('value', $value);
        self::assertSame("SELECT 'a\\'b\\\\c\\0'", $bindings->process('SELECT :value'));
    }

    /** @return list<array{class-string}> */
    public static function stringTypes(): array
    {
        return array_map(static fn (string $class): array => [$class], [
            StringType::class,
            Date::class,
            DateTime::class,
            Enum8::class,
            Enum16::class,
        ]);
    }

    /** @dataProvider legacyStringTypes */
    public function testExistingTypesPreserveRawBindings(string $class): void
    {
        $value = $class::fromString("'already quoted'");
        self::assertSame("'already quoted'", ValueFormatter::formatValue($value));
        self::assertSame("'already quoted'", ValueFormatter::formatValue($value, false));
        $bindings = new Bindings();
        $bindings->bindParam('value', $value);
        self::assertSame("SELECT 'already quoted'", $bindings->process('SELECT :value'));
    }

    /** @dataProvider legacyStringTypes */
    public function testExistingTypesPreserveNativeParameterValues(string $class): void
    {
        $transport = (new \ReflectionClass(\ClickHouseDB\Transport\Http::class))->newInstanceWithoutConstructor();
        $convert = new \ReflectionMethod($transport, 'convertParamValue');
        $convert->setAccessible(true);
        self::assertSame("'raw'", $convert->invoke($transport, $class::fromString("'raw'")));
    }

    /** @return list<array{class-string}> */
    public static function legacyStringTypes(): array
    {
        return [[Date32::class], [DateTime64::class], [UUID::class], [IPv4::class], [IPv6::class]];
    }

    public function testFixedStringRequiresExactByteLength(): void
    {
        self::assertSame('é', FixedString::fromString('é', 2)->getValue());
        self::assertSame("'x'", ValueFormatter::formatValue(FixedString::fromString('x', 1)));
        self::assertSame('ab', FixedString::fromString('ab', 2)->getValue());
        self::assertSame("'a\\'b'", ValueFormatter::formatValue(FixedString::fromString("a'b", 3)));
    }

    /** @dataProvider invalidFixedStrings */
    public function testFixedStringRejectsInvalidLength(string $value, int $length): void
    {
        $this->expectException(InvalidArgumentException::class);
        FixedString::fromString($value, $length);
    }

    /** @return list<array{string, int}> */
    public static function invalidFixedStrings(): array
    {
        return [['', 0], ['', -1], ['', 1], ['x', 2], ['ab', 3], ['é', 1], ['abc', 2]];
    }

    public function testDateFactories(): void
    {
        $date = new DateTimeImmutable('2024-02-29 23:45:12.123456+00:00');
        self::assertSame('2024-02-29', Date::fromDateTime($date)->getValue());
        self::assertSame('2024-02-29', Date32::fromDateTime($date)->getValue());
        self::assertSame('2024-02-29 23:45:12', DateTime::fromDateTime($date)->getValue());
    }

    /** @dataProvider precisions */
    public function testDateTime64Precision(int $precision, string $expected): void
    {
        $date = new DateTimeImmutable('2024-02-29 23:45:12.123456+00:00');
        self::assertSame($expected, DateTime64::fromDateTime($date, $precision)->getValue());
    }

    /** @return list<array{int, string}> */
    public static function precisions(): array
    {
        return [
            [0, '2024-02-29 23:45:12'],
            [1, '2024-02-29 23:45:12.1'],
            [3, '2024-02-29 23:45:12.123'],
            [6, '2024-02-29 23:45:12.123456'],
            [9, '2024-02-29 23:45:12.123456'],
            [-1, '2024-02-29 23:45:12.123456'],
            [10, '2024-02-29 23:45:12.123456'],
        ];
    }

    public function testTimezoneConversionDoesNotMutateInput(): void
    {
        $date = new \DateTime('2024-02-29 23:45:12.123456+00:00');
        self::assertSame('2024-03-01 00:45:12', DateTime::fromDateTime($date, 'Europe/Amsterdam')->getValue());
        self::assertSame('2024-02-29 23:45:12.123456', $date->format('Y-m-d H:i:s.u'));
    }

    public function testNanosecondStringRemainsExact(): void
    {
        self::assertSame('2024-01-01 00:00:00.123456789', DateTime64::fromString('2024-01-01 00:00:00.123456789')->getValue());
    }
}
