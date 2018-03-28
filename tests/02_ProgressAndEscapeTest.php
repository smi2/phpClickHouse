<?php

use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 */
class ProgressAndEscapeTest extends TestCase
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


    public function testProgressFunction()
    {
        global $resultTest;

        $this->setUp();
        $this->db->settings()->set('max_block_size', 1);

        $this->db->progressFunction(function ($data) {
            global $resultTest;
            $resultTest=$data;
        });
        $st=$this->db->select('SELECT number,sleep(0.1) FROM system.numbers limit 4');

        // read_rows + read_bytes + total_rows
        $this->assertArrayHasKey('read_rows',$resultTest);
        $this->assertArrayHasKey('read_bytes',$resultTest);
        $this->assertArrayHasKey('total_rows',$resultTest);

        $this->assertGreaterThan(3,$resultTest['read_rows']);
        $this->assertGreaterThan(3,$resultTest['read_bytes']);

    }

}
