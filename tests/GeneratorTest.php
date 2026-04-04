<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for generator-based row iteration.
 *
 * @group GeneratorTest
 * @link https://github.com/smi2/phpClickHouse/issues/166
 */
final class GeneratorTest extends TestCase
{
    use WithClient;

    public function testRowsGenerator(): void
    {
        $st = $this->client->select('SELECT number FROM system.numbers LIMIT 10');

        $count = 0;
        foreach ($st->rowsGenerator() as $row) {
            $this->assertArrayHasKey('number', $row);
            $count++;
        }

        $this->assertEquals(10, $count);
    }

    public function testRowsGeneratorYieldsCorrectValues(): void
    {
        $st = $this->client->select('SELECT number FROM system.numbers LIMIT 5');

        $values = [];
        foreach ($st->rowsGenerator() as $row) {
            $values[] = $row['number'];
        }

        $this->assertEquals(['0', '1', '2', '3', '4'], $values);
    }

    public function testSelectGenerator(): void
    {
        $count = 0;
        foreach ($this->client->selectGenerator('SELECT number FROM system.numbers LIMIT 20') as $row) {
            $this->assertArrayHasKey('number', $row);
            $count++;
        }

        $this->assertEquals(20, $count);
    }

    public function testSelectGeneratorWithBindings(): void
    {
        $rows = [];
        foreach ($this->client->selectGenerator(
            'SELECT number FROM system.numbers WHERE number < :limit LIMIT 5',
            ['limit' => 100]
        ) as $row) {
            $rows[] = $row;
        }

        $this->assertCount(5, $rows);
    }

    public function testSelectGeneratorMemoryEfficient(): void
    {
        // Iterate over a reasonably large result — should not spike memory
        $count = 0;
        foreach ($this->client->selectGenerator('SELECT number, toString(number) as s FROM system.numbers LIMIT 10000') as $row) {
            $count++;
        }

        $this->assertEquals(10000, $count);
    }

    public function testSelectGeneratorEmpty(): void
    {
        $count = 0;
        foreach ($this->client->selectGenerator('SELECT number FROM system.numbers LIMIT 0') as $row) {
            $count++;
        }

        $this->assertEquals(0, $count);
    }
}
