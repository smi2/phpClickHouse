<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Query\Degeneration\Bindings;
use ClickHouseDB\Quote\ValueFormatter;
use ClickHouseDB\Tests\Fixtures\IntLevel;
use ClickHouseDB\Tests\Fixtures\StringStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function json_decode;

/**
 * Backed enums and other objects exposing ->value must be escaped like scalars.
 */
#[Group('EnumBindingsTest')]
final class EnumBindingsTest extends TestCase
{
    use WithClient;

    // ------------------------------------------------------------ formatting

    public function testStringEnumIsQuoted(): void
    {
        $this->assertSame("'active'", ValueFormatter::formatValue(StringStatus::Active));
        $this->assertSame('active', ValueFormatter::formatValue(StringStatus::Active, false));
    }

    public function testStringEnumValueIsEscaped(): void
    {
        $this->assertSame("'it\\'s'", ValueFormatter::formatValue(StringStatus::Quoted));
    }

    public function testIntEnumStaysNumeric(): void
    {
        $this->assertSame(3, ValueFormatter::formatValue(IntLevel::High));
    }

    public function testObjectWithValuePropertyCannotInjectSql(): void
    {
        $value = json_decode('{"value":"1 OR 1=1"}');

        $this->assertSame("'1 OR 1=1'", ValueFormatter::formatValue($value));

        $value = json_decode('{"value":"x\' OR \'1\'=\'1"}');

        $this->assertSame("'x\\' OR \\'1\\'=\\'1'", ValueFormatter::formatValue($value));
    }

    public function testBindingsRenderEnums(): void
    {
        $bindings = new Bindings();
        $bindings->bindParam('s', StringStatus::Active);
        $bindings->bindParam('l', IntLevel::High);
        $bindings->bindParam('in', [StringStatus::Active, StringStatus::Quoted]);

        $this->assertSame(
            "SELECT 1 WHERE s = 'active' AND l = 3 AND x IN ('active','it\\'s')",
            $bindings->process('SELECT 1 WHERE s = :s AND l = :l AND x IN (:in)')
        );
    }

    public function testRawBindingsUseEnumValue(): void
    {
        $bindings = new Bindings();
        $bindings->bindParam('col', StringStatus::Active);
        $bindings->bindParam('cols', [StringStatus::Active, IntLevel::High]);

        $this->assertSame('SELECT active, active, 3', $bindings->process('SELECT {col}, {cols}'));
    }

    // ----------------------------------------------------------- integration

    public function testSelectWithEnumBindings(): void
    {
        $row = $this->client->select(
            'SELECT :s AS s, :q AS q, :l AS l',
            ['s' => StringStatus::Active, 'q' => StringStatus::Quoted, 'l' => IntLevel::High]
        )->fetchRow();

        $this->assertSame('active', $row['s']);
        $this->assertSame("it's", $row['q']);
        $this->assertSame(3, (int) $row['l']);
    }

    public function testNativeParamsWithEnums(): void
    {
        $row = $this->client->selectWithParams(
            'SELECT {s:String} AS s, {q:String} AS q, {l:UInt8} AS l, {arr:Array(String)} AS arr',
            ['s' => StringStatus::Active, 'q' => StringStatus::Quoted, 'l' => IntLevel::High, 'arr' => [StringStatus::Active]]
        )->fetchRow();

        $this->assertSame('active', $row['s']);
        $this->assertSame("it's", $row['q']);
        $this->assertSame(3, (int) $row['l']);
        $this->assertSame(['active'], $row['arr']);
    }

    public function testInsertEnums(): void
    {
        $this->client->write('DROP TABLE IF EXISTS enum_bindings');
        $this->client->write('CREATE TABLE enum_bindings (s String, l UInt8) ENGINE = Memory');
        try {
            $this->client->insert('enum_bindings', [
                [StringStatus::Active, IntLevel::Low],
                [StringStatus::Quoted, IntLevel::High],
            ], ['s', 'l']);

            $rows = $this->client->select('SELECT s, l FROM enum_bindings ORDER BY l')->rows();

            $this->assertSame(['active', "it's"], array_column($rows, 's'));
            $this->assertSame([1, 3], array_map('intval', array_column($rows, 'l')));
        } finally {
            $this->client->write('DROP TABLE IF EXISTS enum_bindings');
        }
    }
}
