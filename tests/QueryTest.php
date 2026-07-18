<?php
declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Query\Degeneration;
use ClickHouseDB\Query\Query;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    /**
     * Test that isUseInUrlBindingsParams returns true when SQL contains a binding with the format {param:type}
     */
    public function testIsUseInUrlBindingsParamsWithValidBinding(): void
    {
        $sql = 'SELECT {p1:UInt8} + {p2:UInt8}';
        $query = new Query($sql);

        $this->assertEquals('SELECT {p1:UInt8} + {p2:UInt8}', $query->toSql());
        $this->assertTrue($query->isUseInUrlBindingsParams());
    }
    
    /**
     * Test that isUseInUrlBindingsParams returns false when SQL doesn't contain a binding with the format {param:type}
     */
    public function testIsUseInUrlBindingsParamsWithNoBinding(): void
    {
        $sql = 'SELECT 1 + :two';
        $degeneration = new Degeneration\Bindings();
        $degeneration->bindParams([
            'two' => 2,
        ]);
        $query = new Query($sql, [$degeneration]);

        $this->assertEquals('SELECT 1 + 2', $query->toSql());
        $this->assertFalse($query->isUseInUrlBindingsParams());
    }
    
    /**
     * Test that isUseInUrlBindingsParams returns false when SQL contains a similar pattern in a binding value
     */
    public function testIsUseInUrlBindingsParamsWithSimilarPatternInValue(): void
    {
        $sql = 'INSERT INTO a (b) VALUES (:simple_bind)';
        $degeneration = new Degeneration\Bindings();
        $degeneration->bindParams([
            'simple_bind' => '{foo:bar}',
        ]);
        $query = new Query($sql, [$degeneration]);

        $this->assertEquals("INSERT INTO a (b) VALUES ('{foo:bar}')", $query->toSql());
        $this->assertFalse($query->isUseInUrlBindingsParams());
    }
}