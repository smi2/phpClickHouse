<?php

use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 */
class SessionsTest extends TestCase
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


//
    /**
     * @expectedException \ClickHouseDB\DatabaseException
     */
    public function testCreateTableTEMPORARYNoSession()
    {
        $this->setUp();

        $this->db->write('DROP TABLE IF EXISTS phpunti_test_xxxx');
        $this->db->write('
            CREATE TEMPORARY TABLE IF NOT EXISTS phpunti_test_xxxx (
                event_date Date DEFAULT toDate(event_time),
                event_time DateTime,
                url_hash String,
                site_id Int32,
                views Int32
            ) ENGINE = TinyLog
        ');
    }
    public function testUseSession()
    {
        $this->setUp();

        $this->assertFalse( $this->db->getSession() );
        $this->db->useSession();
        $this->assertStringMatchesFormat('%s',$this->db->getSession() );
        $this->setUp();
        $this->assertFalse( $this->db->getSession() );
    }


    public function testCreateTableTEMPORARYWithSessions()
    {

        $this->setUp();

        // make two session tables
        $table_name_A = 'phpunti_test_A_abcd_' . time();
        $table_name_B = 'phpunti_test_B_abcd_' . time();

        // make new session id
        $A_Session_ID = $this->db->useSession()->getSession();

        // create table in session A
        $this->db->write(' CREATE TEMPORARY TABLE IF NOT EXISTS ' . $table_name_A . ' (number UInt64)');
        $this->db->write('INSERT INTO ' . $table_name_A . ' SELECT number FROM system.numbers LIMIT 30');

        $st = $this->db->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_A);
        // check
        $this->assertEquals(14.5, $st->fetchOne('avs'));

        // reconnect + reinit session

        $this->setUp();

        // create table in session B
        $B_Session_ID = $this->db->useSession()->getSession();

        $this->db->write(' CREATE TEMPORARY TABLE IF NOT EXISTS ' . $table_name_B . ' (number UInt64)');

        $this->db->write('INSERT INTO ' . $table_name_B . ' SELECT number*1234 FROM system.numbers LIMIT 30');


        $st = $this->db->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_B);
        // check
        $this->assertEquals(17893, $st->fetchOne('avs'));




        // Reuse session A

        $this->setUp();
        $this->db->useSession($A_Session_ID);

        $st = $this->db->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_A);
        $this->assertEquals(14.5, $st->fetchOne('avs'));


        // Reuse session B

        $this->setUp();
        $this->db->useSession($B_Session_ID);


        $st = $this->db->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_B);
        // check
        $this->assertEquals(17893, $st->fetchOne('avs'));

    }

}
