<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\ClickHouse26;

use ClickHouseDB\Tests\WithClient;
use PHPUnit\Framework\TestCase;

/**
 * Sessions test for ClickHouse 26.x
 * In CH 26.x, temporary tables work without sessions (no exception).
 *
 * @group ClickHouse26
 */
final class SessionsTest extends TestCase
{
    use WithClient;

    public function setUp(): void
    {
        date_default_timezone_set('Europe/Moscow');
        $this->client->ping();
    }

    /**
     * In CH 26.x, creating temporary tables without a session works
     * (unlike CH 21.x which throws an exception).
     */
    public function testCreateTableTEMPORARYNoSessionWorks(): void
    {
        $this->client->write('DROP TABLE IF EXISTS phpunit_ch26_temp_test');
        $this->client->write('
            CREATE TEMPORARY TABLE IF NOT EXISTS phpunit_ch26_temp_test (
                event_date Date DEFAULT toDate(event_time),
                event_time DateTime,
                url_hash String,
                site_id Int32,
                views Int32
            )
        ');

        // Should not throw — CH 26 allows temp tables without sessions
        $this->assertTrue(true);
    }

    public function testUseSession(): void
    {
        $this->assertFalse($this->client->getSession());
        $this->client->useSession();
        $this->assertStringMatchesFormat('%s', $this->client->getSession());
    }

    public function testCreateTableTEMPORARYWithSessions(): void
    {
        $table_name_A = 'phpunit_ch26_test_A_' . time();
        $table_name_B = 'phpunit_ch26_test_B_' . time();

        $A_Session_ID = $this->client->useSession()->getSession();

        $this->client->write('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $table_name_A . ' (number UInt64)');
        $this->client->write('INSERT INTO ' . $table_name_A . ' SELECT number FROM system.numbers LIMIT 30');

        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_A);
        $this->assertEquals(14.5, $st->fetchOne('avs'));

        $B_Session_ID = $this->client->useSession()->getSession();

        $this->client->write('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $table_name_B . ' (number UInt64)');
        $this->client->write('INSERT INTO ' . $table_name_B . ' SELECT number*1234 FROM system.numbers LIMIT 30');

        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_B);
        $this->assertEquals(17893, $st->fetchOne('avs'));

        $this->client->useSession($A_Session_ID);
        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_A);
        $this->assertEquals(14.5, $st->fetchOne('avs'));

        $this->client->useSession($B_Session_ID);
        $st = $this->client->select('SELECT round(avg(number),1) as avs FROM ' . $table_name_B);
        $this->assertEquals(17893, $st->fetchOne('avs'));
    }
}
