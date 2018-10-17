<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Class TableSizeTest
 * @group TableSize
 * @package ClickHouseDB\Tests
 */
final class TableSizeTest extends TestCase
{
    use WithClient;

    public function testPrepareManyRowFail()
    {
        // make two session tables
        $table_name_A = 'phpunti_test_A_ab11cd_' . time();
        $table_name_B = 'phpunti_test_B_ab22cd_' . time();

        // create table in session A
        $this->client->write(' DROP TABLE IF EXISTS ' . $table_name_A . ' ; ');
        $this->client->write(' DROP TABLE IF EXISTS ' . $table_name_B . ' ; ');
        $this->client->write(' CREATE TABLE ' . $table_name_A . ' (number UInt64) ENGINE = Log;');
        $this->client->write(' CREATE TABLE ' . $table_name_B . ' (number UInt64) ENGINE = Log;');
        $this->client->write(' INSERT INTO ' . $table_name_A . ' SELECT number FROM system.numbers LIMIT 30');
        $this->client->write(' INSERT INTO ' . $table_name_B . ' SELECT number FROM system.numbers LIMIT 30');


        $size=$this->client->tablesSize();

        $this->assertArrayHasKey($table_name_A, $size);
        $this->assertArrayHasKey($table_name_B, $size);


        $size=$this->client->tableSize($table_name_A);
        $this->assertArrayHasKey('table', $size);
        $this->assertArrayHasKey('database', $size);
        $this->assertArrayHasKey('sizebytes', $size);
        $this->assertArrayHasKey('size', $size);
        $this->assertArrayHasKey('min_date', $size);
        $this->assertArrayHasKey('max_date', $size);

        $this->client->write(' DROP TABLE IF EXISTS ' . $table_name_A . ' ; ');
        $this->client->write(' DROP TABLE IF EXISTS ' . $table_name_B . ' ; ');


    }
}
