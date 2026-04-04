<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\UnsupportedValueType;
use ClickHouseDB\Query\Expression\Raw;
use ClickHouseDB\Quote\ValueFormatter;
use ClickHouseDB\Type\UInt64;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ValueFormatterTest extends TestCase
{
    public function testNullReturnsNull(): void
    {
        self::assertNull(ValueFormatter::formatValue(null));
    }

    public function testNullWithoutQuotesReturnsNull(): void
    {
        self::assertNull(ValueFormatter::formatValue(null, false));
    }

    public function testInteger(): void
    {
        self::assertSame(42, ValueFormatter::formatValue(42));
    }

    public function testNegativeInteger(): void
    {
        self::assertSame(-100, ValueFormatter::formatValue(-100));
    }

    public function testZero(): void
    {
        self::assertSame(0, ValueFormatter::formatValue(0));
    }

    public function testFloat(): void
    {
        self::assertSame(3.14, ValueFormatter::formatValue(3.14));
    }

    public function testNegativeFloat(): void
    {
        self::assertSame(-0.5, ValueFormatter::formatValue(-0.5));
    }

    public function testBooleanTrue(): void
    {
        self::assertTrue(ValueFormatter::formatValue(true));
    }

    public function testBooleanFalse(): void
    {
        self::assertFalse(ValueFormatter::formatValue(false));
    }

    public function testSimpleString(): void
    {
        self::assertSame("'hello'", ValueFormatter::formatValue('hello'));
    }

    public function testEmptyString(): void
    {
        self::assertSame("''", ValueFormatter::formatValue(''));
    }

    public function testStringWithSingleQuotes(): void
    {
        self::assertSame("'it\\'s'", ValueFormatter::formatValue("it's"));
    }

    public function testStringWithBackslash(): void
    {
        self::assertSame("'path\\\\to'", ValueFormatter::formatValue('path\\to'));
    }

    public function testStringWithDoubleQuotes(): void
    {
        self::assertSame("'say \\\"hi\\\"'", ValueFormatter::formatValue('say "hi"'));
    }

    public function testStringWithMixedSpecialChars(): void
    {
        $input = "it's a \"test\" with \\backslash";
        $result = ValueFormatter::formatValue($input);
        self::assertSame("'it\\'s a \\\"test\\\" with \\\\backslash'", $result);
    }

    public function testStringWithoutQuotes(): void
    {
        self::assertSame('hello', ValueFormatter::formatValue('hello', false));
    }

    public function testStringWithSpecialCharsWithoutQuotes(): void
    {
        // Without quotes, the raw string is returned (no escaping applied)
        self::assertSame("it's", ValueFormatter::formatValue("it's", false));
    }

    public function testDateTimeInterface(): void
    {
        $dt = new DateTimeImmutable('2024-01-15 10:30:45');
        self::assertSame("'2024-01-15 10:30:45'", ValueFormatter::formatValue($dt));
    }

    public function testDateTimeInterfaceWithoutQuotes(): void
    {
        $dt = new DateTimeImmutable('2024-01-15 10:30:45');
        self::assertSame('2024-01-15 10:30:45', ValueFormatter::formatValue($dt, false));
    }

    public function testTypeObject(): void
    {
        $uint = UInt64::fromString('18446744073709551615');
        self::assertSame('18446744073709551615', ValueFormatter::formatValue($uint));
    }

    public function testExpressionObject(): void
    {
        $expr = new Raw("UUIDStringToNum('abc-123')");
        self::assertSame("UUIDStringToNum('abc-123')", ValueFormatter::formatValue($expr));
    }

    public function testObjectWithPublicValueProperty(): void
    {
        $obj = new class {
            public string $value = 'object_value';
        };
        self::assertSame('object_value', ValueFormatter::formatValue($obj));
    }

    public function testObjectWithToString(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'stringified';
            }
        };
        self::assertSame("'stringified'", ValueFormatter::formatValue($obj));
    }

    public function testObjectWithToStringWithoutQuotes(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'stringified';
            }
        };
        self::assertSame('stringified', ValueFormatter::formatValue($obj, false));
    }

    public function testUnsupportedTypeThrowsException(): void
    {
        $this->expectException(UnsupportedValueType::class);

        ValueFormatter::formatValue([1, 2, 3]);
    }

    public function testUnsupportedResourceThrowsException(): void
    {
        $this->expectException(UnsupportedValueType::class);

        $resource = fopen('php://memory', 'r');
        try {
            ValueFormatter::formatValue($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testNumericTypesReturnedAsIs(): void
    {
        // int and float are returned without modification, not cast to string
        self::assertIsInt(ValueFormatter::formatValue(42));
        self::assertIsFloat(ValueFormatter::formatValue(3.14));
        self::assertIsBool(ValueFormatter::formatValue(true));
    }

    /**
     * Type objects take priority over the public $value property check,
     * because instanceof Type is checked first.
     */
    public function testTypeObjectPriorityOverPublicValue(): void
    {
        // UInt64 has public $value AND implements Type — Type branch should win
        $uint = UInt64::fromString('999');
        self::assertSame('999', ValueFormatter::formatValue($uint));
    }
}
