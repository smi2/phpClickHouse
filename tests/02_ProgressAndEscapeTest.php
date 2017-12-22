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


    public function testEscape()
    {
        // chr(0....255);
        $this->db->settings()->set('max_block_size', 100);

        $bind['k1']=1;
        $bind['k2']=2;

        $select=[];
        for($z=0;$z<200;$z++)
        {
            $bind['k'.$z]=chr($z);
            $select[]=":k{$z} as k{$z}";
        }
        arsort($bind);


        $rows=$this->db->select("SELECT ".implode(",\n",$select),$bind)->rows();
        print_r($rows);

    }

    public function testProgressFunction()
    {
        global $resultTest;


        $this->db->settings()->set('max_block_size', 1);
        $this->setUp();

        $this->db->progressFunction(function ($data) {
            global $resultTest;
            $resultTest=$data;
        });
        $st=$this->db->select('SELECT number,sleep(0.2) FROM system.numbers limit 5');

        // read_rows + read_bytes + total_rows
        $this->assertArrayHasKey('read_rows',$resultTest);
        $this->assertArrayHasKey('read_bytes',$resultTest);
        $this->assertArrayHasKey('total_rows',$resultTest);

        $this->assertGreaterThan(3,$resultTest['read_rows']);
        $this->assertGreaterThan(3,$resultTest['read_bytes']);

    }

}
