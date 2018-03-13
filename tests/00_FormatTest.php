<?php

use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 */
class FormatQueryTest extends TestCase
{
    /**
     * @var \ClickHouseDB\Client
     */
    private $db;

    /**
     * @var
     */
    private $tmp_path;

    /**
     * @throws Exception
     */
    public function setUp()
    {
        date_default_timezone_set('Europe/Moscow');

        if (!defined('phpunit_clickhouse_host')) {
            throw new Exception("Not set phpunit_clickhouse_host in phpUnit.xml");
        }

        $tmp_path = rtrim(phpunit_clickhouse_tmp_path, '/') . '/';

        if (!is_dir($tmp_path)) {
            throw  new Exception("Not dir in phpunit_clickhouse_tmp_path");
        }

        $this->tmp_path = $tmp_path;

        $config = [
            'host' => phpunit_clickhouse_host,
            'port' => phpunit_clickhouse_port,
            'username' => phpunit_clickhouse_user,
            'password' => phpunit_clickhouse_pass,

        ];

        $this->db = new ClickHouseDB\Client($config);


        $this->db->ping();
    }

    /**
     *
     */
    public function tearDown()
    {
        //
    }



    public function testCreateTableTEMPORARYNoSession()
    {
        $this->setUp();



        $query="SELECT 2*number as FORMAT FROM system.numbers LIMIT 1,1 format TSV";
        $st = $this->db->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('TSV', $st->getFormat());
        $this->assertEquals("2\n", $st->rawData());



        $query="SELECT number as format_id FROM system.numbers LIMIT 3 FORMAT CSVWithNames";
        $st = $this->db->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('CSVWithNames', $st->getFormat());
        $this->assertEquals("\"format_id\"\n0\n1\n2\n", $st->rawData());



        $query="SELECT number as format_id FROM system.numbers LIMIT 1,1 FORMAT CSV";
        $st = $this->db->select($query);
        $this->assertEquals($query, $st->sql());
        $this->assertEquals('CSV', $st->getFormat());

        //


    }


}
