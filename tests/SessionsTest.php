<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 * @group SessionsTest
 */
final class SessionsTest extends TestCase
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
        $this->expectException(DatabaseException::class);

        $this->client->write('DROP TABLE IF EXISTS phpunti_test_xxxx');
        $this->client->write('
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
        $this->assertFalse($this->client->getSession());
        $this->client->useSession();
        $this->assertStringMatchesFormat('%s',$this->client->getSession());
    }


    public function testCreateTableTEMPORARYWithSessions()
    {
        // make two session tables
        $table_name_A = 'phpunti_test_A_abcd_' . time();
        $table_name_B = 'phpunti_test_B_abcd_' . time();

        // make new session id
        $A_Session_ID = $this->client->useSession()->getSession();

        // create table in session A
        $this->client->write(' CREATE TEMPORARY TABLE IF NOT EXISTS ' . $table_name_A . ' (number UInt64)');
        $this->client->write('INSERT INTO ' . $table_name_A . ' SELECT number FROM system.numbers LIMIT 30');

        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_A);
        // check
        $this->assertEquals(14.5, $st->fetchOne('avs'));

        // reconnect + reinit session

        // create table in session B
        $B_Session_ID = $this->client->useSession()->getSession();

        $this->client->write(' CREATE TEMPORARY TABLE IF NOT EXISTS ' . $table_name_B . ' (number UInt64)');

        $this->client->write('INSERT INTO ' . $table_name_B . ' SELECT number*1234 FROM system.numbers LIMIT 30');

        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_B);
        // check
        $this->assertEquals(17893, $st->fetchOne('avs'));




        // Reuse session A

        $this->client->useSession($A_Session_ID);

        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_A);
        $this->assertEquals(14.5, $st->fetchOne('avs'));


        // Reuse session B

        $this->client->useSession($B_Session_ID);


        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_B);
        // check
        $this->assertEquals(17893, $st->fetchOne('avs'));
    }
}
