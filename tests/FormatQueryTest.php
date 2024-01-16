<?php

namespace ClickHouseDB\Tests;
 
use PHPUnit\Framework\TestCase;

/**
 * Class FormatQueryTest
 * @package ClickHouseDB\Tests
 * @group FormatQueryTest
 */
final class FormatQueryTest extends TestCase
{
    use WithClient;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        date_default_timezone_set('Europe/Moscow');

        $this->client->ping();
    }

    public function testCreateTableTEMPORARYNoSession()
    {
        $query="SELECT 2*number as FORMAT FROM system.numbers LIMIT 1,1 format TSV";
        $st = $this->client->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('TSV', $st->getFormat());
        $this->assertEquals("2\n", $st->rawData());



        $query="SELECT number as format_id FROM system.numbers LIMIT 3 FORMAT CSVWithNames";
        $st = $this->client->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('CSVWithNames', $st->getFormat());
        $this->assertEquals("\"format_id\"\n0\n1\n2\n", $st->rawData());




        $query="SELECT number as format_id FROM system.numbers LIMIT 1,1 FORMAT CSV";
        $st = $this->client->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('CSV', $st->getFormat());

        $query="SELECT number as format_id FROM number(2) LIMIT 1,1 FORMAT TSVWithNamesAndTypes";
        $st = $this->client->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('TSVWithNamesAndTypes', $st->getFormat());

    }

    public function testClientTimeoutSettings()
    {
        // https://github.com/smi2/phpClickHouse/issues/168
        // Only setConnectTimeOut & getConnectTimeOut can be float
        // max_execution_time - is integer in clickhouse source - Seconds
        $this->client->database('default');

        $timeout = 0.55; // un support, "clickhouse source - Seconds"
        $this->client->setTimeout($timeout);      // 550 ms
        $this->client->select('SELECT 123,123 as ping ')->rows();
        $this->assertSame(intval($timeout), intval($this->client->getTimeout()));

        $timeout = 2.55; // un support, "clickhouse source - Seconds"
        $this->client->setTimeout($timeout);      // 550 ms
        $this->client->select('SELECT 123,123 as ping ')->rows();
        $this->assertSame(intval($timeout), intval($this->client->getTimeout()));

        $timeout = 10.0;
        $this->client->setTimeout($timeout);      // 10 seconds
        $this->client->select('SELECT 123,123 as ping ')->rows();
        $this->assertSame(intval($timeout), $this->client->getTimeout());


        // getConnectTimeOut is curl, can be float
        $timeout = 5.14;
        $this->client->setConnectTimeOut($timeout);      // 5 seconds
        $this->client->select('SELECT 123,123 as ping ')->rows();


        $this->assertSame(5.14, $this->client->getConnectTimeOut());

    }




}
