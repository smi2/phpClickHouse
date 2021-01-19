<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Query\WhereInFile;
use ClickHouseDB\Query\WriteToFile;
use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerRolling;
use ClickHouseDB\Transport\StreamInsert;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 * @package ClickHouseDB\Tests
 * @group ClientTest
 */
class ClientTest extends TestCase
{
    use WithClient;

    public function setUp(): void
    {
        date_default_timezone_set('Europe/Moscow');

        $this->client->enableHttpCompression(true);
        $this->client->ping();
    }

    /**
     *
     */
    public function tearDown(): void
    {
        //
    }

    /**
     * @return \ClickHouseDB\Statement
     */
    private function insert_data_table_summing_url_views()
    {
        $databaseName = getenv('CLICKHOUSE_DATABASE');
        return $this->client->insert(

            $databaseName.'.summing_url_views',
            [
                [strtotime('2010-10-10 00:00:00'), 'HASH1', 2345, 22, 20, 2],
                [strtotime('2010-10-11 01:00:00'), 'HASH2', 2345, 12, 9, 3],
                [strtotime('2010-10-12 02:00:00'), 'HASH3', 5345, 33, 33, 0],
                [strtotime('2010-10-13 03:00:00'), 'HASH4', 5345, 55, 12, 55],
            ],
            ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']
        );
    }

    /**
     * @param $file_name
     * @param int $size
     */
    private function create_fake_csv_file($file_name, $size = 1)
    {
        $this->create_fake_file($file_name, $size);
    }

    /**
     * @param $file_name
     * @param int $size
     */
    private function create_fake_json_file($file_name, $size = 1)
    {
        $this->create_fake_file($file_name, $size, 'JSON');
    }

    /**
     * @param $file_name
     * @param int $size
     * @param string $file_type
     */
    private function create_fake_file($file_name, $size = 1, $file_type = 'CSV')
    {
        if (is_file($file_name)) {
            unlink($file_name);
        }

        $handle = fopen($file_name, 'w');

        $z = 0;
        $rows = 0;

        for ($dates = 0; $dates < $size; $dates++) {
            for ($site_id = 10; $site_id < 99; $site_id++) {
                for ($hours = 0; $hours < 12; $hours++) {
                    $z++;

                    $dt = strtotime('-' . $dates . ' day');
                    $dt = strtotime('-' . $hours . ' hour', $dt);

                    $j = [];
                    $j['event_time'] = date('Y-m-d H:00:00', $dt);
                    $j['url_hash'] = 'x' . $site_id . 'x' . $size;
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
                            fputcsv($handle, $j);
                    }
                    $rows++;
                }
            }
        }

        fclose($handle);
    }

    /**
     * @return \ClickHouseDB\Statement
     */
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
            ) ENGINE = SummingMergeTree(event_date, (site_id, url_hash, event_time, event_date), 8192)
        ');
    }







    public function testInsertNullable()
    {
        $this->client->write('DROP TABLE IF EXISTS `test`');
        $this->client->write('CREATE TABLE `test` (
                event_date Date DEFAULT toDate(event_time),
                event_time DateTime,
                url_hash Nullable(String)
        ) ENGINE = TinyLog()');
        $this->client->insert(
            'test',
            [
                [strtotime('2010-10-10 00:00:00'), null],
            ],
            ['event_time', 'url_hash']
        );

        $statement = $this->client->select('SELECT url_hash FROM `test`');
        self::assertCount(1, $statement->rows());
        self::assertNull($statement->fetchOne('url_hash'));

    }

    public function testInsertDotTable()
    {
        $databaseName = getenv('CLICKHOUSE_DATABASE');

        $this->client->write("DROP TABLE IF EXISTS `tsts.test`");
        $this->client->write('CREATE TABLE `tsts.test` (
                event_date Date DEFAULT toDate(event_time),
                event_time DateTime,
                url_hash String,
                site_id Int32,
                views Int32,
                v_00 Int32,
                v_55 Int32
        ) ENGINE = TinyLog()');
        $this->client->insert(
            '`tsts.test`',
            [
                [strtotime('2010-10-10 00:00:00'), 'Хеш', 2345, 22, 20, 2],
            ],
            ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']
        );

        $this->client->insert(
            $databaseName.'.`tsts.test`',
            [
                [strtotime('2010-10-10 00:00:00'), 'Хеш', 2345, 22, 20, 2],
            ],
            ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']
        );

//        $this->client->verbose();
        $st=$this->client->select('SELECT  url_hash FROM `tsts.test` WHERE like(url_hash,\'%Хеш%\') ');
        $this->assertEquals('Хеш', $st->fetchOne('url_hash'));

    }

    public function testSearchWithCyrillic()
    {
        $this->create_table_summing_url_views();
        $this->client->insert(
            'summing_url_views',
            [
                [strtotime('2010-10-10 00:00:00'), 'Хеш', 2345, 22, 20, 2],
                [strtotime('2010-10-11 01:00:00'), 'Хущ', 2345, 12, 9, 3],
                [strtotime('2010-10-12 02:00:00'), 'Хищ', 5345, 33, 33, 0],
                [strtotime('2010-10-13 03:00:00'), 'Русский язык', 5345, 55, 12, 55],
            ],
            ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']
        );

//        $this->client->verbose();
        $st=$this->client->select('SELECT  url_hash FROM summing_url_views WHERE like(url_hash,\'%Русский%\') ');
        $this->assertEquals('Русский язык', $st->fetchOne('url_hash'));

    }




    public function testInsertNestedArray()
    {

        $this->client->write("DROP TABLE IF EXISTS NestedNested_test");

        $this->client->write('
    CREATE TABLE IF NOT EXISTS NestedNested_test (
        s_key String,
        topics Nested( id UInt8 , ww Float32 ),
        s_arr Array(String)
    ) ENGINE = Memory
');


        //
        $TestArrayPHP=['AAA'."'".'A',"BBBBB".'\\'];
        $this->client->insert('NestedNested_test', [
            ['HASH\1', [11,33],[3.2,2.1],$TestArrayPHP],
        ], ['s_key', 'topics.id','topics.ww','s_arr']);

        // wait read  [0] => AAA'A  [1] => BBBBB\



        $st=$this->client->select('SELECT cityHash64(s_arr) as hash FROM NestedNested_test');
        $this->assertEquals('3072250716474788897', $st->fetchOne('hash'));


        $row=$this->client->select('SELECT * FROM NestedNested_test ARRAY JOIN topics WHERE topics.id=11')->fetchOne();

        $this->assertEquals(11, $row['topics.id']);
        $this->assertEquals(3.2, $row['topics.ww']);
        $this->assertEquals($TestArrayPHP, $row['s_arr']);




    }
    public function testRFCCSVAndTSVWrite()
    {

        $check_hash='5774439760453101066';

        $fileName=$this->tmpPath.'__testRFCCSVWrite';

        $array_value_test="\n1\n2's'";

        $this->client->write("DROP TABLE IF EXISTS testRFCCSVWrite");
        $this->client->write('CREATE TABLE testRFCCSVWrite ( 
           event_date Date DEFAULT toDate(event_time),
           event_time DateTime,
           strs String,
           flos Float32,
           ints Int32,
           arr1 Array(UInt8),  
           arrs Array(String)  
        ) ENGINE = TinyLog()');

        @unlink($fileName);

        $data=[
            ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME STRING','flos'=>1.1,'ints'=>1,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
            ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME STRING','flos'=>2.3,'ints'=>2,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
            ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME\'STRING','flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
            ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"SOMET\nRI\n\"N\"G\\XX_ABCDEFG",'flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC"]],
            ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"ID_ARRAY",'flos'=>0,'ints'=>0,'arr1'=>[1,2,4],'arrs'=>["A","B\nD\nC",$array_value_test]]
        ];

        // 1.1 + 2.3 = 3.3999999761581
        //
        foreach ($data as $row)
        {
            file_put_contents($fileName,FormatLine::CSV($row)."\n",FILE_APPEND);
        }

        $this->client->insertBatchFiles('testRFCCSVWrite', [$fileName], [
            'event_time',
            'strs',
            'flos',
            'ints',
            'arr1',
            'arrs',
        ]);



        $st=$this->client->select('SELECT sipHash64(strs) as hash FROM testRFCCSVWrite WHERE like(strs,\'%ABCDEFG%\') ');

        $this->assertEquals($check_hash, $st->fetchOne('hash'));

        $ID_ARRAY=$this->client->select('SELECT * FROM testRFCCSVWrite WHERE strs=\'ID_ARRAY\'')->fetchOne('arrs')[2];

        $this->assertEquals($array_value_test, $ID_ARRAY);


        $row=$this->client->select('SELECT round(sum(flos),1) as flos,round(sum(ints),1) as ints FROM testRFCCSVWrite')->fetchOne();

        $this->assertEquals(3, $row['ints']);
        $this->assertEquals(3.4, $row['flos']);


        unlink($fileName);


        $this->client->write("DROP TABLE IF EXISTS testRFCCSVWrite");
        $this->client->write('CREATE TABLE testRFCCSVWrite ( 
           event_date Date DEFAULT toDate(event_time),
           event_time DateTime,
           strs String,
           flos Float32,
           ints Int32,
           arr1 Array(UInt8),  
           arrs Array(String)  
        ) ENGINE = Log');



        foreach ($data as $row)
        {
            file_put_contents($fileName,FormatLine::TSV($row)."\n",FILE_APPEND);
        }

        $this->client->insertBatchTSVFiles('testRFCCSVWrite', [$fileName], [
            'event_time',
            'strs',
            'flos',
            'ints',
            'arr1',
            'arrs',
        ]);




        $row=$this->client->select('SELECT round(sum(flos),1) as flos,round(sum(ints),1) as ints FROM testRFCCSVWrite')->fetchOne();

        $st=$this->client->select('SELECT sipHash64(strs) as hash FROM testRFCCSVWrite WHERE like(strs,\'%ABCDEFG%\') ');


        $this->assertEquals($check_hash, $st->fetchOne('hash'));

        $ID_ARRAY=$this->client->select('SELECT * FROM testRFCCSVWrite WHERE strs=\'ID_ARRAY\'')->fetchOne('arrs')[2];

        $this->assertEquals($array_value_test, $ID_ARRAY);


        $row=$this->client->select('SELECT round(sum(flos),1) as flos,round(sum(ints),1) as ints FROM testRFCCSVWrite')->fetchOne();

        $this->assertEquals(3, $row['ints']);
        $this->assertEquals(3.4, $row['flos']);
        $this->client->write("DROP TABLE IF EXISTS testRFCCSVWrite");
        unlink($fileName);
        return true;
    }
    public function testConnectTimeout()
    {
        $config = [
            'host'     => '8.8.8.8', // fake ip , use googlde DNS )
            'port'     => 8123,
            'username' => '',
            'password' => ''
        ];
        $start_time=microtime(true);

        try {
            $db = new Client($config);
            $db->setConnectTimeOut(1);
            $db->ping();
        } catch (\Exception $e) {
        }

        $use_time=round(microtime(true)-$start_time);
        $this->assertEquals(1, $use_time);

    }
    /**
     *
     */
    public function testGzipInsert()
    {
        $file_data_names = [
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data',
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.2.data',
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.3.data',
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.4.data'
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->create_table_summing_url_views();

        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->client->select('SELECT sum(views) as sum_x,min(v_00) as min_x FROM summing_url_views');
        $this->assertEquals(8544, $st->fetchOne('sum_x'));

        $st = $this->client->select('SELECT * FROM summing_url_views ORDER BY url_hash');
        $this->assertEquals(8544, $st->count());

        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }


    public function testWriteToFileSelect()
    {
        $file=$this->tmpPath.'__chdrv_testWriteToFileSelect.csv';


        $file_data_names = [
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 1);
        }

        $this->create_table_summing_url_views();

        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);
        $this->client->ping();

        $write=new WriteToFile($file);
        $this->client->select('select * from summing_url_views limit 4',[],null,$write);
        $this->assertEquals(208,$write->size());

        $write=new WriteToFile($file,true,WriteToFile::FORMAT_TabSeparated);
        $this->client->select('select * from summing_url_views limit 4',[],null,$write);
        $this->assertEquals(184,$write->size());


        $write=new WriteToFile($file,true,WriteToFile::FORMAT_TabSeparatedWithNames);
        $this->client->select('select * from summing_url_views limit 4',[],null,$write);
        $this->assertEquals(239,$write->size());

        unlink($file);
        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }

    }

    /**
     * @expectedException \ClickHouseDB\Exception\QueryException
     */
    public function testInsertCSVError()
    {
        $this->expectException(\ClickHouseDB\Exception\QueryException::class);

        $file_data_names = [
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data'
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->create_table_summing_url_views();
        $this->client->enableHttpCompression(true);

        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }

    /**
     * @param $file_name
     * @param $array
     */
    private function make_csv_SelectWhereIn($file_name, $array)
    {
        if (is_file($file_name)) {
            unlink($file_name);
        }

        $handle = fopen($file_name, 'w');
        foreach ($array as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    public function testSelectWhereIn()
    {
        $this->create_table_summing_url_views();

        $file_data_names = [
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data'
        ];

        $file_name_where_in1 = $this->tmpPath . '_testSelectWhereIn.1.data';
        $file_name_where_in2 = $this->tmpPath . '_testSelectWhereIn.2.data';

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->client->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');
        $this->assertEquals(2136, $st->fetchOne('sum_x'));


        $whereIn_1 = [
            [85, 'x85x2'],
            [69, 'x69x2'],
            [20, 'x20x2'],
            [11, 'xxxxx'],
            [12, 'zzzzz']
        ];

        $whereIn_2 = [
            [11, 'x11x2'],
            [12, 'x12x1'],
            [13, 'x13x2'],
            [14, 'xxxxx'],
            [15, 'zzzzz']
        ];

        $this->make_csv_SelectWhereIn($file_name_where_in1, $whereIn_1);
        $this->make_csv_SelectWhereIn($file_name_where_in2, $whereIn_2);

        $whereIn = new WhereInFile();

        $whereIn->attachFile($file_name_where_in1, 'whin1', [
            'site_id'  => 'Int32',
            'url_hash' => 'String'
        ], WhereInFile::FORMAT_CSV);

        $whereIn->attachFile($file_name_where_in2, 'whin2', [
            'site_id'  => 'Int32',
            'url_hash' => 'String'
        ], WhereInFile::FORMAT_CSV);

        $result = $this->client->select('
        SELECT 
          url_hash,
          site_id,
          sum(views) as views 
        FROM summing_url_views 
        WHERE 
        (site_id,url_hash) IN (SELECT site_id,url_hash FROM whin1)
        or
        (site_id,url_hash) IN (SELECT site_id,url_hash FROM whin2)
        GROUP BY url_hash,site_id
        ', [], $whereIn);

        $result = $result->rowsAsTree('site_id');


        $this->assertEquals(11, $result['11']['site_id']);
        $this->assertEquals(20, $result['20']['site_id']);
        $this->assertEquals(24, $result['13']['views']);
        $this->assertEquals('x20x2', $result['20']['url_hash']);
        $this->assertEquals('x85x2', $result['85']['url_hash']);
        $this->assertEquals('x69x2', $result['69']['url_hash']);

        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }

    /**
     *
     */
    public function testInsertCSV()
    {
        $file_data_names = [
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data',
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.2.data',
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.3.data'
        ];


        // --- make
        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->create_table_summing_url_views();
        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);


        $st = $this->client->select('SELECT sum(views) as sum_x,min(v_00) as min_x FROM summing_url_views');
        $this->assertEquals(6408, $st->fetchOne('sum_x'));

        $st = $this->client->select('SELECT * FROM summing_url_views ORDER BY url_hash');
        $this->assertEquals(6408, $st->count());

        $st = $this->client->select('SELECT * FROM summing_url_views LIMIT 4');

        $this->assertGreaterThan(4, $st->countAll());


        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->client->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');
        $this->assertEquals(2 * 6408, $st->fetchOne('sum_x'));

        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }

    /**
     *
     */
    public function testPing()
    {
        $result = $this->client->select('SELECT 12 as {key} WHERE {key} = :value', ['key' => 'ping', 'value' => 12]);
        $this->assertEquals(12, $result->fetchOne('ping'));
    }

    /**
     *
     */
    public function testSelectAsync()
    {
        $state1 = $this->client->selectAsync('SELECT 1 as {key} WHERE {key} = :value', ['key' => 'ping', 'value' => 1]);
        $state2 = $this->client->selectAsync('SELECT 2 as ping');

        $this->client->executeAsync();

        $this->assertEquals(1, $state1->fetchOne('ping'));
        $this->assertEquals(2, $state2->fetchOne('ping'));
    }

    /**
     *
     */
    public function testInfoRaw()
    {
        $this->create_table_summing_url_views();
        $this->insert_data_table_summing_url_views();
        $this->client->enableExtremes(true);

        $state = $this->client->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');

        $this->client->enableExtremes(false);

        $this->assertFalse($state->isError());

        $this->assertArrayHasKey('starttransfer_time',$state->info());
        $this->assertArrayHasKey('size_download',$state->info());
        $this->assertArrayHasKey('speed_download',$state->info());
        $this->assertArrayHasKey('size_upload',$state->info());
        $this->assertArrayHasKey('upload_content',$state->info());
        $this->assertArrayHasKey('speed_upload',$state->info());
        $this->assertArrayHasKey('time_request',$state->info());

        $rawData=($state->rawData());

        $this->assertArrayHasKey('rows',$rawData);
        $this->assertArrayHasKey('meta',$rawData);
        $this->assertArrayHasKey('data',$rawData);
        $this->assertArrayHasKey('extremes',$rawData);


        $responseInfo=($state->responseInfo());
        $this->assertArrayHasKey('url',$responseInfo);
        $this->assertArrayHasKey('content_type',$responseInfo);
        $this->assertArrayHasKey('http_code',$responseInfo);
        $this->assertArrayHasKey('request_size',$responseInfo);
        $this->assertArrayHasKey('filetime',$responseInfo);
        $this->assertArrayHasKey('total_time',$responseInfo);
        $this->assertArrayHasKey('upload_content_length',$responseInfo);
        $this->assertArrayHasKey('primary_ip',$responseInfo);
        $this->assertArrayHasKey('local_ip',$responseInfo);


        $this->assertEquals(200, $responseInfo['http_code']);

    }

    public function testTableExists()
    {
        $this->create_table_summing_url_views();

        $this->assertEquals('summing_url_views', $this->client->showTables()['summing_url_views']['name']);

        $this->client->write("DROP TABLE IF EXISTS summing_url_views");
    }

    public function testExceptionWrite()
    {
        $this->expectException(DatabaseException::class);

        $this->client->write("DRAP TABLEX")->isError();
    }

    public function testExceptionInsert()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(60);

        $this->client->insert('bla_bla', [
            ['HASH1', [11, 22, 33]],
            ['HASH1', [11, 22, 55]],
        ], ['s_key', 's_arr']);
    }

    public function testExceptionInsertNoData() : void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Inserting empty values array is not supported in ClickHouse');

        $this->client->insert('bla_bla', []);
    }

    public function testExceptionSelect()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(60);

        $this->client->select("SELECT * FROM XXXXX_SSS")->rows();
    }

    public function testExceptionConnects()
    {
        $config = [
            'host'     => 'x',
            'port'     => '8123',
            'username' => 'x',
            'password' => 'x',
            'settings' => ['max_execution_time' => 100]
        ];

        $db = new Client($config);
        $this->assertFalse($db->ping());
    }

    public function testSettings()
    {
        $config = [
            'host'     => 'x',
            'port'     => '8123',
            'username' => 'x',
            'password' => 'x',
        ];

        $settings = ['max_execution_time' => 100];

        $db = new Client($config, $settings);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));


        $config = [
            'host' => 'x',
            'port' => '8123',
            'username' => 'x',
            'password' => 'x'
        ];
        $db = new Client($config);
        $db->settings()->set('max_execution_time', 100);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));


        $config = [
            'host' => 'x',
            'port' => '8123',
            'username' => 'x',
            'password' => 'x'
        ];
        $db = new Client($config);
        $db->settings()->apply([
            'max_execution_time' => 100,
            'max_block_size' => 12345
        ]);

        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));
        $this->assertEquals(12345, $db->settings()->getSetting('max_block_size'));
    }

    public function testWriteEmpty()
    {
        $this->expectException(QueryException::class);

        $this->client->write('');
    }

    public function testInsertArrayTable()
    {
        $this->client->write("DROP TABLE IF EXISTS arrays_test_ints");
        $this->client->write('
            CREATE TABLE IF NOT EXISTS arrays_test_ints
            (
                s_key String,
                s_arr Array(UInt8)
            ) 
            ENGINE = Memory
        ');


        $state = $this->client->insert('arrays_test_ints', [
            ['HASH1', [11, 33]],
            ['HASH2', [11, 55]],
        ], ['s_key', 's_arr']);

        $this->assertGreaterThan(0, $state->totalTimeRequest());

        $state = $this->client->select('SELECT s_arr,s_key FROM arrays_test_ints ARRAY JOIN s_arr ');

        $this->assertEquals(4, $state->count());

        $state = $this->client->select('SELECT s_arr,s_key FROM arrays_test_ints ARRAY JOIN s_arr WHERE s_key=\'HASH1\' AND s_arr=33 ORDER BY s_arr,s_key');

        $this->assertEquals(1, $state->count());
        $this->assertEquals([['s_arr' => 33,'s_key' => 'HASH1']], $state->rows());
    }

    public function testInsertTableTimeout()
    {
        $this->expectException(QueryException::class);

        $this->create_table_summing_url_views();

        $file_data_names = [
            $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 10);
        }

        $this->create_table_summing_url_views();


        $this->client->setTimeout(0.01);


        $stat = $this->client->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);
        $this->client->ping();
    }
    /**
     *
     */
    public function testInsertTable()
    {
        $this->create_table_summing_url_views();

        $state = $this->insert_data_table_summing_url_views();

        $this->assertFalse($state->isError());


        $st = $this->client->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');

        $this->assertEquals(122, $st->fetchOne('sum_x'));
        $this->assertEquals(9, $st->fetchOne('min_x'));
        $this->client->enableExtremes(true);
        $st = $this->client->select('SELECT * FROM summing_url_views ORDER BY url_hash');

        $this->client->enableExtremes(false);


        $this->assertEquals(4, $st->count());
        $this->assertEquals(0, $st->countAll());
        $this->assertNull($st->totals());

        $this->assertEquals('HASH1', $st->fetchOne()['url_hash']);
        $this->assertEquals(2345, $st->extremesMin()['site_id']);

        $st = $this->client->select('
            SELECT url_hash, sum(views) as vv, avg(views) as avgv 
            FROM summing_url_views 
            WHERE site_id < 3333 
            GROUP BY url_hash 
            WITH TOTALS
        ');


        $this->assertEquals(2, $st->count());
        $this->assertEquals(0, $st->countAll());

        $this->assertEquals(34, $st->totals()['vv']);
        $this->assertEquals(17, $st->totals()['avgv']);


        $this->assertEquals(22, $st->rowsAsTree('url_hash')['HASH1']['vv']);

        // drop
        $this->client->write("DROP TABLE IF EXISTS summing_url_views");
    }

    /**
     *
     */
    public function testStreamInsert()
    {
        $this->create_table_summing_url_views();

        $file_name = $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data';
        $this->create_fake_csv_file($file_name, 1);

        $source = fopen($file_name, 'rb');
        $request = $this->client->insertBatchStream('summing_url_views', [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $curlerRolling = new CurlerRolling();
        $streamInsert = new StreamInsert($source, $request, $curlerRolling);

        $callable = function ($ch, $fd, $length) use ($source) {
            return ($line = fread($source, $length)) ? $line : '';
        };
        $streamInsert->insert($callable);

        // check the resource was close after insert method
        $this->assertEquals(false, is_resource($source));

        $statement = $this->client->select('SELECT * FROM summing_url_views');
        $this->assertEquals(count(file($file_name)), $statement->count());
    }

    /**
     *
     */
    public function testStreamInsertExeption()
    {
        $file_name = $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data';
        $this->create_fake_csv_file($file_name, 1);

        $source = fopen($file_name, 'rb');
        $curlerRolling = new CurlerRolling();
        $streamInsert = new StreamInsert($source, new CurlerRequest(), $curlerRolling);

        $this->expectException(InvalidArgumentException::class);
        $streamInsert->insert([]);
    }

    /**
     *
     */
    public function testStreamInsertExceptionResourceIsClose()
    {
        $file_name = $this->tmpPath . '_testInsertCSV_clickHouseDB_test.1.data';
        $this->create_fake_csv_file($file_name, 1);

        $source = fopen($file_name, 'rb');
        $streamInsert = new StreamInsert($source, new CurlerRequest());
        try {
            $streamInsert->insert([]);
        } catch (\Exception $e) {}

        // check the resource was close after insert method
        $this->assertEquals(false, is_resource($source));
    }

    public function testUptime()
    {
        $uptime = $this->client->getServerUptime();
        $this->assertGreaterThan(1,$uptime);
    }

    public function testVersion()
    {
        $version = $this->client->getServerVersion();
        $this->assertMatchesRegularExpression('/(^[0-9]+.[0-9]+.[0-9]+.*$)/mi', $version);
    }

    public function testServerSystemSettings()
    {
        $up = $this->client->getServerSystemSettings('merge_tree_min_rows_for_concurrent_read');
        $this->assertGreaterThan(1,$up['merge_tree_min_rows_for_concurrent_read']['value']);
    }

    /**
     *
     */
    public function testStreamInsertFormatJSONEachRow()
    {
        $file_name = $this->tmpPath . '_testStreamInsertJSON_clickHouseDB_test.data';
        $this->create_fake_json_file($file_name, 1);

        $this->create_table_summing_url_views();

        $source = fopen($file_name, 'rb');
        $request = $this->client->insertBatchStream('summing_url_views', [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ], 'JSONEachRow');

        $curlerRolling = new CurlerRolling();
        $streamInsert = new StreamInsert($source, $request, $curlerRolling);

        $callable = function ($ch, $fd, $length) use ($source) {
            return ($line = fread($source, $length)) ? $line : '';
        };
        $streamInsert->insert($callable);

        $statement = $this->client->select('SELECT * FROM summing_url_views');
        $this->assertEquals(count(file($file_name)), $statement->count());
    }

}
