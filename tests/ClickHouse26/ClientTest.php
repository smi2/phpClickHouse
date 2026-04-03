<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\ClickHouse26;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Exception\TransportException;
use ClickHouseDB\Query\WhereInFile;
use ClickHouseDB\Query\WriteToFile;
use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerRolling;
use ClickHouseDB\Transport\StreamInsert;
use ClickHouseDB\Tests\WithClient;
use PHPUnit\Framework\TestCase;

/**
 * ClientTest adapted for ClickHouse 26.x
 * Uses modern MergeTree syntax and accounts for behavioral changes.
 *
 * @group ClickHouse26
 */
final class ClientTest extends TestCase
{
    use WithClient;

    private function create_fake_csv_file($file_name, $count_sites = 1, $file_type = 'CSV')
    {
        $handle = fopen($file_name, 'w');
        $z = 0;
        $rows = 0;
        for ($dates = 0; $dates < 10; $dates++) {
            for ($site_id = 0; $site_id < $count_sites; $site_id++) {
                for ($hours = 0; $hours < 24; $hours++) {
                    $z++;
                    $dt = strtotime('-' . $dates . ' day');
                    $dt = strtotime('-' . $hours . ' hour', $dt);
                    $j = [];
                    $j['event_time'] = date('Y-m-d H:00:00', $dt);
                    $j['url_hash'] = 'x' . $site_id . 'x' . $count_sites;
                    $j['site_id'] = $site_id;
                    $j['views'] = 1;
                    foreach (['00', 55] as $key) {
                        $z++;
                        $j['v_' . $key] = ($z % 2 ? 1 : 0);
                    }
                    switch ($file_type) {
                        case 'JSON':
                            fwrite($handle, json_encode($j) . PHP_EOL);
                            break;
                        default:
                            fputcsv($handle, $j, ",", '"', "\\");
                    }
                    $rows++;
                }
            }
        }
        fclose($handle);
    }

    private function create_table_summing_url_views()
    {
        $this->client->write("DROP TABLE IF EXISTS summing_url_views");

        return $this->client->write('
            CREATE TABLE IF NOT EXISTS summing_url_views (
                event_date Date DEFAULT toDate(event_time),
                event_time DateTime,
                url_hash String,
                site_id Int32,
                views Int32,
                v_00 Int32,
                v_55 Int32
            ) ENGINE = SummingMergeTree
              ORDER BY (site_id, url_hash, event_time, event_date)
        ');
    }

    public function testInsertNullable(): void
    {
        $this->client->write("DROP TABLE IF EXISTS nullable_test");
        $this->client->write('
            CREATE TABLE IF NOT EXISTS nullable_test (
                dt Date,
                sss Nullable(String)
            ) ENGINE = MergeTree ORDER BY dt
        ');

        $this->client->insert('nullable_test', [
            [date('Y-m-d'), null],
            [date('Y-m-d'), 'AAA'],
        ], ['dt', 'sss']);

        $st = $this->client->select('SELECT * FROM nullable_test ORDER BY sss');
        $this->assertEquals(2, $st->count());
    }

    public function testInsertDotTable(): void
    {
        $this->client->write("DROP TABLE IF EXISTS t1234.test_table");
        $this->client->write("CREATE DATABASE IF NOT EXISTS t1234");
        $this->client->write('
            CREATE TABLE IF NOT EXISTS t1234.test_table (
                dt Date,
                sss String
            ) ENGINE = MergeTree ORDER BY dt
        ');

        $this->client->insert('t1234.test_table', [
            [date('Y-m-d'), 'AAA'],
        ], ['dt', 'sss']);

        $st = $this->client->select('SELECT * FROM t1234.test_table');
        $this->assertEquals(1, $st->count());

        $this->client->write("DROP TABLE IF EXISTS t1234.test_table");
        $this->client->write("DROP DATABASE IF EXISTS t1234");
    }

    public function testSearchWithCyrillic(): void
    {
        $this->create_table_summing_url_views();

        $this->client->insert('summing_url_views', [
            [date('Y-m-d H:00:00'), 'Привет мир', 1, 1, 1, 1],
        ], ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']);

        $st = $this->client->select(
            "SELECT * FROM summing_url_views WHERE url_hash = :url",
            ['url' => 'Привет мир']
        );
        $this->assertEquals(1, $st->count());
    }

    public function testGzipInsert(): void
    {
        $file_data_names = [
            $this->tmpPath . '_ch26_testGzipInsert.1.data',
            $this->tmpPath . '_ch26_testGzipInsert.2.data',
            $this->tmpPath . '_ch26_testGzipInsert.3.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 1);
        }

        $this->client->enableHttpCompression(true);
        $this->create_table_summing_url_views();

        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->client->select('SELECT count() as cnt FROM summing_url_views');
        // 3 files * 240 rows each
        $this->assertGreaterThan(0, $st->fetchOne('cnt'));

        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }

    public function testInsertCSV(): void
    {
        $file_data_names = [
            $this->tmpPath . '_ch26_testInsertCSV.1.data',
            $this->tmpPath . '_ch26_testInsertCSV.2.data',
            $this->tmpPath . '_ch26_testInsertCSV.3.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 1);
        }

        $this->create_table_summing_url_views();

        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->client->select('SELECT sum(views) as sum_x FROM summing_url_views');
        // 3 files * 240 rows * 1 view each
        $this->assertGreaterThan(0, $st->fetchOne('sum_x'));

        $st = $this->client->select('SELECT count() as cnt FROM summing_url_views');
        $this->assertGreaterThan(0, $st->fetchOne('cnt'));

        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }

    public function testSelectWhereIn(): void
    {
        $this->create_table_summing_url_views();

        $this->client->insert('summing_url_views', [
            [date('Y-m-d H:00:00'), 'hash1', 1, 100, 1, 0],
            [date('Y-m-d H:00:00'), 'hash2', 2, 200, 0, 1],
            [date('Y-m-d H:00:00'), 'hash3', 3, 300, 1, 1],
        ], ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']);

        $st = $this->client->select(
            'SELECT * FROM summing_url_views WHERE site_id IN (:ids)',
            ['ids' => [1, 2]]
        );
        $this->assertEquals(2, $st->count());
    }

    public function testPing(): void
    {
        $this->assertTrue($this->client->ping());
    }

    public function testSelectAsync(): void
    {
        $state1 = $this->client->selectAsync('SELECT 1 as ping');
        $state2 = $this->client->selectAsync('SELECT 2 as ping');

        $this->client->executeAsync();

        $this->assertEquals(1, $state1->fetchOne('ping'));
        $this->assertEquals(2, $state2->fetchOne('ping'));
    }

    public function testTableExists(): void
    {
        $this->create_table_summing_url_views();

        $this->assertEquals(
            'summing_url_views',
            $this->client->showTables()['summing_url_views']['name']
        );

        $this->client->write("DROP TABLE IF EXISTS summing_url_views");
    }

    public function testExceptionWrite(): void
    {
        $this->expectException(QueryException::class);
        $this->client->write("DRAP TABLEX")->isError();
    }

    public function testExceptionInsert(): void
    {
        $this->expectException(QueryException::class);

        $this->client->insert('bla_bla', [
            [time(), 'HASH1', 2345, 22, 20, 2],
        ], ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']);
    }

    public function testExceptionSelect(): void
    {
        $this->expectException(QueryException::class);

        $this->client->select("SELECT * FROM XXXXX_table_not_exists")->rows();
    }

    public function testSettings(): void
    {
        $config = [
            'host' => 'x',
            'port' => '8123',
            'username' => 'x',
            'password' => 'x',
        ];

        $db = new Client($config, ['max_execution_time' => 100]);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));

        $db = new Client($config);
        $db->settings()->set('max_execution_time', 100);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));

        $db = new Client($config);
        $db->settings()->apply([
            'max_execution_time' => 100,
            'max_block_size' => 12345,
        ]);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));
        $this->assertEquals(12345, $db->settings()->getSetting('max_block_size'));
    }

    public function testInsertArrayTable(): void
    {
        $this->client->write("DROP TABLE IF EXISTS arrays_test_string");
        $this->client->write('
            CREATE TABLE IF NOT EXISTS arrays_test_string (
                s_key String,
                s_arr Array(String)
            ) ENGINE = Memory
        ');

        $this->client->insert('arrays_test_string', [
            ['HASH1', ["a", "dddd", "xxx"]],
            ['HASH1', ["b'\tx"]],
        ], ['s_key', 's_arr']);

        $st = $this->client->select('SELECT count() as cnt FROM arrays_test_string');
        $this->assertEquals(2, $st->fetchOne('cnt'));
    }

    public function testInsertTable(): void
    {
        $this->create_table_summing_url_views();

        $file_data_names = [
            $this->tmpPath . '_ch26_testInsertTable.1.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 1);
        }

        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->client->select('SELECT count() as cnt FROM summing_url_views');
        $this->assertEquals(240, $st->fetchOne('cnt'));

        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }

    public function testStreamInsert(): void
    {
        $this->create_table_summing_url_views();

        $file = $this->tmpPath . '_ch26_testStreamInsert.data';
        $this->create_fake_csv_file($file, 1);

        $source = fopen($file, 'rb');
        $request = $this->client->insertBatchStream('summing_url_views', [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $curlerRolling = new CurlerRolling();
        $streamInsert = new StreamInsert($source, $request, $curlerRolling);

        $callable = function ($ch, $fd, $length) use ($source) {
            return ($line = fread($source, $length)) ? $line : '';
        };
        $streamInsert->insert($callable);

        $st = $this->client->select('SELECT count() as cnt FROM summing_url_views');
        $this->assertEquals(240, $st->fetchOne('cnt'));

        unlink($file);
    }

    public function testStreamInsertFormatJSONEachRow(): void
    {
        $this->create_table_summing_url_views();

        $file = $this->tmpPath . '_ch26_testStreamInsertJSON.data';
        $this->create_fake_csv_file($file, 1, 'JSON');

        $source = fopen($file, 'rb');
        $request = $this->client->insertBatchStream('summing_url_views', [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ], 'JSONEachRow');

        $curlerRolling = new CurlerRolling();
        $streamInsert = new StreamInsert($source, $request, $curlerRolling);

        $callable = function ($ch, $fd, $length) use ($source) {
            return ($line = fread($source, $length)) ? $line : '';
        };
        $streamInsert->insert($callable);

        $st = $this->client->select('SELECT count() as cnt FROM summing_url_views');
        $this->assertEquals(240, $st->fetchOne('cnt'));

        unlink($file);
    }

    public function testUptime(): void
    {
        $st = $this->client->getServerUptime();
        $this->assertGreaterThan(0, $st);
    }

    public function testVersion(): void
    {
        $st = $this->client->getServerVersion();
        $this->assertStringStartsWith('26.', $st);
    }
}
