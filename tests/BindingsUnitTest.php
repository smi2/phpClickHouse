<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Query\Degeneration\Bindings;
use ClickHouseDB\Query\Degeneration\Conditions;
use PHPUnit\Framework\TestCase;

class BindingsUnitTest extends TestCase
{
    public function testCompileBindsReplacesNamedPlaceholders(): void
    {
        $bindings = new Bindings();
        $result = $bindings->compile_binds(
            'SELECT :col FROM table',
            ['col' => 'replaced_value'],
            '#:([\w+]+)#'
        );
        self::assertSame('SELECT replaced_value FROM table', $result);
    }

    public function testCompileBindsLeavesUnknownPlaceholdersUnchanged(): void
    {
        $bindings = new Bindings();
        $result = $bindings->compile_binds(
            'SELECT :known, :unknown FROM table',
            ['known' => 'found'],
            '#:([\w+]+)#'
        );
        self::assertSame('SELECT found, :unknown FROM table', $result);
    }

    public function testCompileBindsHandlesMultiplePlaceholders(): void
    {
        $bindings = new Bindings();
        $result = $bindings->compile_binds(
            'SELECT * FROM t WHERE a = :a AND b = :b AND c = :c',
            ['a' => '1', 'b' => '2', 'c' => '3'],
            '#:([\w+]+)#'
        );
        self::assertSame('SELECT * FROM t WHERE a = 1 AND b = 2 AND c = 3', $result);
    }

    public function testProcessWithStringBindings(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams(['name' => "O'Brien"]);
        $result = $bindings->process('SELECT * FROM t WHERE name = :name');
        self::assertSame("SELECT * FROM t WHERE name = 'O\'Brien'", $result);
    }

    public function testProcessWithIntBindings(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams(['id' => 42]);
        $result = $bindings->process('SELECT * FROM t WHERE id = :id');
        self::assertSame('SELECT * FROM t WHERE id = 42', $result);
    }

    public function testProcessWithArrayBindingsExpandsToInList(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams(['ids' => [1, 2, 3]]);
        $result = $bindings->process('SELECT * FROM t WHERE id IN (:ids)');
        self::assertSame('SELECT * FROM t WHERE id IN (1,2,3)', $result);
    }

    public function testProcessWithArrayStringBindings(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams(['names' => ['Alice', 'Bob']]);
        $result = $bindings->process("SELECT * FROM t WHERE name IN (:names)");
        self::assertSame("SELECT * FROM t WHERE name IN ('Alice','Bob')", $result);
    }

    public function testProcessWithCurlyBraceBindings(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams(['table' => 'users']);
        $result = $bindings->process('SELECT * FROM {table}');
        self::assertSame('SELECT * FROM users', $result);
    }

    /**
     * @see https://github.com/smi2/phpClickHouse/issues/256
     */
    public function testBindParamsWithNumericKeys(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams(['value1', 'value2']);
        $bind = $bindings->getBind();
        self::assertSame(['0' => 'value1', '1' => 'value2'], $bind);
    }

    /**
     * @see https://github.com/smi2/phpClickHouse/issues/256
     */
    public function testBindParamsWithMixedKeys(): void
    {
        $bindings = new Bindings();
        $bindings->bindParams([0 => 'first', 'name' => 'Alice', 1 => 'second']);
        $bind = $bindings->getBind();
        self::assertSame(['0' => 'first', 'name' => 'Alice', '1' => 'second'], $bind);
    }

    public function testConditionsProcessIfBlockWithTruthyMarker(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams(['active' => 1]);
        $result = $conditions->process('SELECT * FROM t {if active}WHERE active = 1{/if}');
        self::assertSame('SELECT * FROM t WHERE active = 1', $result);
    }

    public function testConditionsProcessIfBlockWithFalsyMarker(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams(['active' => 0]);
        $result = $conditions->process('SELECT * FROM t {if active}WHERE active = 1{/if}');
        // 0 is numeric, so condition is truthy per __ifsets logic
        self::assertSame('SELECT * FROM t WHERE active = 1', $result);
    }

    public function testConditionsProcessIfElseBlockTruthy(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams(['order' => 'name']);
        $result = $conditions->process('SELECT * FROM t ORDER BY {if order}name{else}id{/if}');
        self::assertSame('SELECT * FROM t ORDER BY name', $result);
    }

    public function testConditionsProcessIfElseBlockFalsy(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams(['order' => '']);
        $result = $conditions->process('SELECT * FROM t ORDER BY {if order}name{else}id{/if}');
        self::assertSame('SELECT * FROM t ORDER BY id', $result);
    }

    public function testConditionsProcessUnsetMarkerRemovesBlock(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams([]);
        $result = $conditions->process('SELECT * FROM t {if filter}WHERE x = 1{/if}');
        self::assertSame('SELECT * FROM t ', $result);
    }

    public function testConditionsProcessUnsetMarkerWithElseKeepsElse(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams([]);
        $result = $conditions->process('SELECT * FROM t ORDER BY {if order}name{else}id{/if}');
        self::assertSame('SELECT * FROM t ORDER BY id', $result);
    }

    public function testConditionsIfSetPreset(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams(['filter' => 'value']);
        $result = $conditions->process('SELECT * FROM t {ifset filter}WHERE x = 1{/if}');
        self::assertSame('SELECT * FROM t WHERE x = 1', $result);
    }

    public function testConditionsIfSetPresetEmpty(): void
    {
        $conditions = new Conditions();
        $conditions->bindParams(['filter' => '']);
        $result = $conditions->process('SELECT * FROM t {ifset filter}WHERE x = 1{/if}');
        self::assertSame('SELECT * FROM t ', $result);
    }
}
