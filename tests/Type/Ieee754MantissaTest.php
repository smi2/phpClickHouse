<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Type;

use ClickHouseDB\Tests\WithClient;
use ClickHouseDB\Type\Decimal128;
use ClickHouseDB\Type\Decimal32;
use ClickHouseDB\Type\Decimal64;
use ClickHouseDB\Type\Float32;
use PHPUnit\Framework\TestCase;

use function is_float;
use function is_int;
use function sprintf;
use function version_compare;

/**
 * IEEE 754 significand (mantissa) behavior of every float type and the exact
 * (non-IEEE) behavior of the Decimal family, as returned over the JSON format.
 *
 * Significand sizes: Float32 — 24 bits (integers exact up to 2^24 = 16777216),
 * Float64 — 53 bits (up to 2^53 = 9007199254740992), BFloat16 — 8 bits (up to
 * 2^8 = 256). Decimal stores scaled integers and has no mantissa at all.
 *
 * @group integration
 */
final class Ieee754MantissaTest extends TestCase
{
    use WithClient;

    private function serverVersion(): string
    {
        return (string) $this->client->select('SELECT version() AS v')->fetchOne('v');
    }

    private function requireBFloat16(): void
    {
        if (version_compare($this->serverVersion(), '24.11', '<')) {
            self::markTestSkipped('BFloat16 requires ClickHouse 24.11+');
        }
    }

    private function requireBareDecimalSyntax(): void
    {
        if (version_compare($this->serverVersion(), '22.1', '<')) {
            self::markTestSkipped('Bare Decimal / Decimal(P) syntax is not supported on ClickHouse 21.x');
        }
    }

    // ---------------------------------------------------------------- Float32

    public function testFloat32IntegerAtMantissaLimitIsExact(): void
    {
        self::assertSame(16777216, $this->client->select('SELECT toFloat32(16777216) AS v')->fetchOne('v'));
    }

    public function testFloat32IntegerBeyondMantissaLimitCollapses(): void
    {
        // 16777217 = 2^24 + 1 does not fit into the 24-bit significand.
        self::assertSame(16777216, $this->client->select('SELECT toFloat32(16777217) AS v')->fetchOne('v'));
    }

    public function testFloat32StoredMantissaDiffersFromDecimalLiteral(): void
    {
        $row = $this->client->select(
            'SELECT toFloat64(toFloat32(1.1)) AS stored, toFloat32(1.1) = 1.1 AS eq'
        )->fetchRow();

        self::assertSame(1.100000023841858, $row['stored']);
        self::assertSame(0, $row['eq']);
    }

    public function testFloat32JsonUsesShortestRoundTripRepresentation(): void
    {
        // The stored bits are 1.10000002384…, but JSON prints the shortest
        // string that round-trips back to the same Float32 — literally "1.1".
        self::assertSame(1.1, $this->client->select('SELECT toFloat32(1.1) AS v')->fetchOne('v'));
    }

    /**
     * @dataProvider dyadicFractions
     */
    public function testFloat32DyadicFractionRoundTripsExactly(string $literal, float $expected): void
    {
        $this->client->write('DROP TABLE IF EXISTS ieee754_f32');
        $this->client->write('CREATE TABLE ieee754_f32 (value Float32) ENGINE = Memory');
        try {
            $this->client->insert('ieee754_f32', [[Float32::fromString($literal)]], ['value']);

            self::assertSame($expected, $this->client->select('SELECT value FROM ieee754_f32')->fetchOne('value'));
        } finally {
            $this->client->write('DROP TABLE IF EXISTS ieee754_f32');
        }
    }

    /** @return array<string, array{string, float}> */
    public static function dyadicFractions(): array
    {
        // Sums of powers of two are exactly representable in any IEEE type.
        return [
            'half' => ['0.5', 0.5],
            'quarter' => ['0.25', 0.25],
            'eighth' => ['0.125', 0.125],
            'mixed' => ['3.75', 3.75],
        ];
    }

    // ---------------------------------------------------------------- Float64

    public function testFloat64IntegerAtMantissaLimitIsExact(): void
    {
        self::assertSame(
            9007199254740992,
            $this->client->select('SELECT toFloat64(9007199254740992) AS v')->fetchOne('v')
        );
    }

    public function testFloat64IntegerBeyondMantissaLimitCollapses(): void
    {
        // 2^53 + 1 does not fit into the 53-bit significand.
        self::assertSame(
            9007199254740992,
            $this->client->select('SELECT toFloat64(9007199254740993) AS v')->fetchOne('v')
        );
    }

    public function testFloat64ClassicSumExposesMantissaError(): void
    {
        $row = $this->client->select('SELECT 0.1 + 0.2 AS v, (0.1 + 0.2 = 0.3) AS eq')->fetchRow();

        self::assertSame(0.30000000000000004, $row['v']);
        self::assertSame(0, $row['eq']);
    }

    public function testFloat64SumOfDyadicFractionsIsExact(): void
    {
        $row = $this->client->select('SELECT 0.5 + 0.25 AS v, (0.5 + 0.25 = 0.75) AS eq')->fetchRow();

        self::assertSame(0.75, $row['v']);
        self::assertSame(1, $row['eq']);
    }

    public function testFloat64InfinityAndNanBecomeNullInJson(): void
    {
        $row = $this->client->select(
            'SELECT toFloat64(1) / 0 AS inf, 0 / toFloat64(0) AS nan'
        )->fetchRow();

        self::assertNull($row['inf']);
        self::assertNull($row['nan']);
    }

    // --------------------------------------------------------------- BFloat16

    public function testBFloat16IntegerAtMantissaLimitIsExact(): void
    {
        $this->requireBFloat16();

        self::assertSame(256, $this->client->select('SELECT toBFloat16(256) AS v')->fetchOne('v'));
    }

    public function testBFloat16IntegerBeyondMantissaLimitCollapses(): void
    {
        $this->requireBFloat16();

        // 257 = 2^8 + 1 does not fit into the 8-bit significand.
        self::assertSame(256, $this->client->select('SELECT toBFloat16(257) AS v')->fetchOne('v'));
    }

    public function testBFloat16FractionRoundsCoarsely(): void
    {
        $this->requireBFloat16();

        // Only 8 significand bits: 1.1 becomes 1.09375 (= 1 + 3/32).
        self::assertSame(1.09375, $this->client->select('SELECT toBFloat16(1.1) AS v')->fetchOne('v'));
    }

    public function testBFloat16DyadicFractionRoundTripsThroughTable(): void
    {
        $this->requireBFloat16();

        $this->client->write('DROP TABLE IF EXISTS ieee754_bf16');
        $this->client->write('CREATE TABLE ieee754_bf16 (value BFloat16) ENGINE = Memory');
        try {
            $this->client->insert('ieee754_bf16', [[1.5]], ['value']);

            self::assertSame(1.5, $this->client->select('SELECT value FROM ieee754_bf16')->fetchOne('value'));
        } finally {
            $this->client->write('DROP TABLE IF EXISTS ieee754_bf16');
        }
    }

    // ---------------------------------------------------- Decimal (no mantissa)

    public function testDecimalSumIsExactUnlikeFloat64(): void
    {
        $row = $this->client->select(
            "SELECT toDecimal64('0.1', 1) + toDecimal64('0.2', 1) AS v," .
            " toString(toDecimal64('0.1', 1) + toDecimal64('0.2', 1)) AS s," .
            " (toDecimal64('0.1', 1) + toDecimal64('0.2', 1) = toDecimal64('0.3', 1)) AS eq"
        )->fetchRow();

        self::assertSame(0.3, $row['v']);
        self::assertSame('0.3', $row['s']);
        self::assertSame(1, $row['eq']);
    }

    public function testBareDecimalDefaultsToPrecision10Scale0(): void
    {
        $this->requireBareDecimalSyntax();

        $row = $this->client->select(
            "SELECT CAST('1.5', 'Decimal') AS v, toTypeName(CAST('1.5', 'Decimal')) AS t"
        )->fetchRow();

        self::assertSame('Decimal(10, 0)', $row['t']);
        self::assertSame(1, $row['v']);
    }

    public function testDecimalWithPrecisionOnlyMeansScaleZero(): void
    {
        $this->requireBareDecimalSyntax();

        self::assertSame(
            'Decimal(10, 0)',
            $this->client->select("SELECT toTypeName(CAST('1.7', 'Decimal(10)')) AS t")->fetchOne('t')
        );
    }

    public function testDecimalScaleZeroTruncatesTowardZero(): void
    {
        $row = $this->client->select(
            "SELECT CAST('1.7', 'Decimal(10, 0)') AS pos, CAST('-1.7', 'Decimal(10, 0)') AS neg"
        )->fetchRow();

        self::assertSame(1, $row['pos']);
        self::assertSame(-1, $row['neg']);
    }

    public function testDecimalStoresExactValueWithoutTrailingZeros(): void
    {
        $row = $this->client->select(
            "SELECT toDecimal64('1.1', 4) AS v, toString(toDecimal64('1.1', 4)) AS s"
        )->fetchRow();

        self::assertSame(1.1, $row['v']);
        self::assertSame('1.1', $row['s']);
    }

    public function testDecimalArrivesAsJsonNumberNotString(): void
    {
        $row = $this->client->select(
            "SELECT toDecimal32('1.1', 2) AS frac, CAST('2', 'Decimal(10, 0)') AS whole"
        )->fetchRow();

        // All supported server versions emit Decimal as an unquoted JSON
        // number, so PHP receives a float (or an int for scale-0 values) —
        // NOT a string.
        self::assertTrue(is_float($row['frac']));
        self::assertTrue(is_int($row['whole']));
    }

    public function testDecimal32RoundTripsAtFullNineDigitPrecision(): void
    {
        self::assertSame(
            '1234567.89',
            $this->client->select("SELECT toString(toDecimal32('1234567.89', 2)) AS s")->fetchOne('s')
        );
    }

    public function testDecimal64PreservesDigitsBeyondFloat64Mantissa(): void
    {
        // 18 significant digits: more than the 15–16 digits Float64 can hold.
        $row = $this->client->select(
            "SELECT toString(toDecimal64('123456789012345.678', 3)) AS dec," .
            " toString(toFloat64('123456789012345.678')) AS flt"
        )->fetchRow();

        self::assertSame('123456789012345.678', $row['dec']);
        self::assertNotSame('123456789012345.678', $row['flt']);
    }

    public function testDecimal128PreservesThirtyEightDigits(): void
    {
        self::assertSame(
            '1234567890123456789.1234567890123456789',
            $this->client->select(
                "SELECT toString(toDecimal128('1234567890123456789.1234567890123456789', 19)) AS s"
            )->fetchOne('s')
        );
    }

    public function testWideDecimalDecodedFromJsonLosesPrecisionInPhp(): void
    {
        // The server emits the full 38-digit JSON number, but json_decode()
        // parses it into a PHP float (53-bit mantissa). Exact values must be
        // read via toString() — this test documents the trap.
        $value = $this->client->select(
            "SELECT toDecimal128('1234567890123456789.1234567890123456789', 19) AS v"
        )->fetchOne('v');

        self::assertTrue(is_float($value));
        self::assertSame(1.2345678901234568E+18, $value);
    }

    /**
     * @dataProvider decimalWrapperColumns
     */
    public function testDecimalWrapperRoundTripPreservesExactString(
        string $columnType,
        string $className,
        string $literal
    ): void {
        $this->client->write('DROP TABLE IF EXISTS ieee754_decimal');
        $this->client->write(sprintf('CREATE TABLE ieee754_decimal (value %s) ENGINE = Memory', $columnType));
        try {
            $this->client->insert('ieee754_decimal', [[$className::fromString($literal)]], ['value']);

            self::assertSame(
                $literal,
                $this->client->select('SELECT toString(value) AS s FROM ieee754_decimal')->fetchOne('s')
            );
        } finally {
            $this->client->write('DROP TABLE IF EXISTS ieee754_decimal');
        }
    }

    /** @return array<string, array{string, class-string, string}> */
    public static function decimalWrapperColumns(): array
    {
        return [
            'Decimal32(S)' => ['Decimal32(2)', Decimal32::class, '1234567.89'],
            'Decimal64(S)' => ['Decimal64(3)', Decimal64::class, '123456789012345.678'],
            'Decimal128(S)' => ['Decimal128(19)', Decimal128::class, '1234567890123456789.1234567890123456789'],
            'Decimal(P, S)' => ['Decimal(38, 19)', Decimal128::class, '1234567890123456789.1234567890123456789'],
        ];
    }
}
