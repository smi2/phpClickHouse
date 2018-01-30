<?php

use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 */
class ClientTest extends TestCase
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
            'host'     => phpunit_clickhouse_host,
            'port'     => phpunit_clickhouse_port,
            'username' => phpunit_clickhouse_user,
            'password' => phpunit_clickhouse_pass
        ];

        $this->db = new ClickHouseDB\Client($config);
        $this->db->enableHttpCompression(true);
        $this->db->ping();
    }

    /**
     *
     */
    public function tearDown()
    {
        //
    }

    /**
     * @return \ClickHouseDB\Statement
     */
    private function insert_data_table_summing_url_views()
    {
        return $this->db->insert(
            'summing_url_views',
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

                    fputcsv($handle, $j);
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
        $this->db->write("DROP TABLE IF EXISTS summing_url_views");

        return $this->db->write('
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




    /**
     *
     */
    public function testSqlConditions()
    {
        $input_params = [
            'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
            'limit'       => 5,
            'from_table'  => 'table_x_y',
            'idid'        => 0,
            'false'       => false
        ];

        $this->assertEquals(
            'SELECT * FROM table_x_y FORMAT JSON',
            $this->db->selectAsync('SELECT * FROM {from_table}', $input_params)->sql()
        );

        $this->assertEquals(
            'SELECT * FROM table_x_y WHERE event_date IN (\'2000-10-10\',\'2000-10-11\',\'2000-10-12\') FORMAT JSON',
            $this->db->selectAsync('SELECT * FROM {from_table} WHERE event_date IN (:select_date)', $input_params)->sql()
        );

        $this->db->enableQueryConditions();

        $this->assertEquals(
            'SELECT * FROM ZZZ LIMIT 5 FORMAT JSON',
            $this->db->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', $input_params)->sql()
        );

        $this->assertEquals(
            'SELECT * FROM ZZZ NOOPE FORMAT JSON',
            $this->db->selectAsync('SELECT * FROM ZZZ {if nope}LIMIT {limit}{else}NOOPE{/if}', $input_params)->sql()
        );
        $this->assertEquals(
            'SELECT * FROM 0 FORMAT JSON',
            $this->db->selectAsync('SELECT * FROM :idid', $input_params)->sql()
        );


        $this->assertEquals(
            'SELECT * FROM  FORMAT JSON',
            $this->db->selectAsync('SELECT * FROM :false', $input_params)->sql()
        );


        $keys=[
            'key1'=>1,
            'key111'=>111,
            'key11'=>11,
            'key123' => 123,
        ];


        $this->assertEquals(
            '123=123 , 11=11, 111=111, 1=1, 1= 1, 123=123 FORMAT JSON',
            $this->db->selectAsync('123=:key123 , 11={key11}, 111={key111}, 1={key1}, 1= :key1, 123=:key123', $keys)->sql()
        );


        $isset=[
            'FALSE'=>false,
            'ZERO'=>0,
            'NULL'=>null

        ];

        $this->assertEquals(
            '|ZERO||  FORMAT JSON',
            $this->db->selectAsync('{if FALSE}FALSE{/if}|{if ZERO}ZERO{/if}|{if NULL}NULL{/if}| ' ,$isset)->sql()
        );




    }



    public function testSqlDisableConditions()
    {

        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if} FORMAT JSON',  $this->db->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', [])->sql());
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT 123{/if} FORMAT JSON',  $this->db->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());
        $this->db->cleanQueryDegeneration();
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if} FORMAT JSON',  $this->db->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());
        $this->setUp();
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT 123{/if} FORMAT JSON',  $this->db->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());


    }



    public function testSearchWithCyrillic()
    {
        $this->create_table_summing_url_views();
        $this->db->insert(
            'summing_url_views',
            [
                [strtotime('2010-10-10 00:00:00'), 'Хеш', 2345, 22, 20, 2],
                [strtotime('2010-10-11 01:00:00'), 'Хущ', 2345, 12, 9, 3],
                [strtotime('2010-10-12 02:00:00'), 'Хищ', 5345, 33, 33, 0],
                [strtotime('2010-10-13 03:00:00'), 'Русский язык', 5345, 55, 12, 55],
            ],
            ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']
        );

//        $this->db->verbose();
        $st=$this->db->select('SELECT  url_hash FROM summing_url_views WHERE like(url_hash,\'%Русский%\') ');
        $this->assertEquals('Русский язык', $st->fetchOne('url_hash'));

    }




    public function testRFCCSVAndTSVWrite()
    {
        $fileName=$this->tmp_path.'__testRFCCSVWrite';

        $array_value_test="\n1\n2's'";

        $this->db->write("DROP TABLE IF EXISTS testRFCCSVWrite");
        $this->db->write('CREATE TABLE testRFCCSVWrite ( 
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
            ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"ID_ARRAY",'flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC",$array_value_test]]
        ];

        // 1.1 + 2.3 = 3.3999999761581
        //
        foreach ($data as $row)
        {
            file_put_contents($fileName,\ClickHouseDB\FormatLine::CSV($row)."\n",FILE_APPEND);
        }

        $this->db->insertBatchFiles('testRFCCSVWrite', [$fileName], [
            'event_time',
            'strs',
            'flos',
            'ints',
            'arr1',
            'arrs',
        ]);

        $st=$this->db->select('SELECT sipHash64(strs) as hash FROM testRFCCSVWrite WHERE like(strs,\'%ABCDEFG%\') ');


        $this->assertEquals('5774439760453101066', $st->fetchOne('hash'));

        $ID_ARRAY=$this->db->select('SELECT * FROM testRFCCSVWrite WHERE strs=\'ID_ARRAY\'')->fetchOne('arrs')[2];

        $this->assertEquals($array_value_test, $ID_ARRAY);



        $row=$this->db->select('SELECT round(sum(flos),1) as flos,round(sum(ints),1) as ints FROM testRFCCSVWrite')->fetchOne();

        $this->assertEquals(3, $row['ints']);
        $this->assertEquals(3.4, $row['flos']);


        unlink($fileName);


        $this->db->write("DROP TABLE IF EXISTS testRFCCSVWrite");
        $this->db->write('CREATE TABLE testRFCCSVWrite ( 
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
            file_put_contents($fileName,\ClickHouseDB\FormatLine::TSV($row)."\n",FILE_APPEND);
        }

        $this->db->insertBatchTSVFiles('testRFCCSVWrite', [$fileName], [
            'event_time',
            'strs',
            'flos',
            'ints',
            'arr1',
            'arrs',
        ]);




        $row=$this->db->select('SELECT round(sum(flos),1) as flos,round(sum(ints),1) as ints FROM testRFCCSVWrite')->fetchOne();

        $st=$this->db->select('SELECT sipHash64(strs) as hash FROM testRFCCSVWrite WHERE like(strs,\'%ABCDEFG%\') ');


        $this->assertEquals('17721988568158798984', $st->fetchOne('hash'));

        $ID_ARRAY=$this->db->select('SELECT * FROM testRFCCSVWrite WHERE strs=\'ID_ARRAY\'')->fetchOne('arrs')[2];

        $this->assertEquals($array_value_test, $ID_ARRAY);



        $row=$this->db->select('SELECT round(sum(flos),1) as flos,round(sum(ints),1) as ints FROM testRFCCSVWrite')->fetchOne();

        $this->assertEquals(3, $row['ints']);
        $this->assertEquals(3.4, $row['flos']);
        $this->db->write("DROP TABLE IF EXISTS testRFCCSVWrite");
        unlink($fileName);
        return true;
    }
    public function testConnectTimeout()
    {
        $config = [
            'host'     => '8.8.8.8', // fake ip , use googlde DNS )
            'port'     => phpunit_clickhouse_port,
            'username' => '',
            'password' => ''
        ];
        $start_time=microtime(true);

        try
        {
            $db = new ClickHouseDB\Client($config);
            $db->setConnectTimeOut(1);
            $db->ping();
        }
        catch (Exception $E)
        {

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
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data',
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.2.data',
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.3.data',
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.4.data'
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->create_table_summing_url_views();

        $stat = $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->db->select('SELECT sum(views) as sum_x,min(v_00) as min_x FROM summing_url_views');
        $this->assertEquals(8544, $st->fetchOne('sum_x'));

        $st = $this->db->select('SELECT * FROM summing_url_views ORDER BY url_hash');
        $this->assertEquals(8544, $st->count());

        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }
    }


    public function testWriteToFileSelect()
    {
        $file=$this->tmp_path.'__chdrv_testWriteToFileSelect.csv';


        $file_data_names = [
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 1);
        }

        $this->create_table_summing_url_views();

        $stat = $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);
        $this->db->ping();

        $write=new ClickHouseDB\WriteToFile($file);
        $this->db->select('select * from summing_url_views limit 4',[],null,$write);
        $this->assertEquals(208,$write->size());

        $write=new ClickHouseDB\WriteToFile($file,true,ClickHouseDB\WriteToFile::FORMAT_TabSeparated);
        $this->db->select('select * from summing_url_views limit 4',[],null,$write);
        $this->assertEquals(184,$write->size());


        $write=new ClickHouseDB\WriteToFile($file,true,ClickHouseDB\WriteToFile::FORMAT_TabSeparatedWithNames);
        $this->db->select('select * from summing_url_views limit 4',[],null,$write);
        $this->assertEquals(239,$write->size());

        unlink($file);
        // --- drop
        foreach ($file_data_names as $file_name) {
            unlink($file_name);
        }

    }

    /**
     * @expectedException \ClickHouseDB\DatabaseException
     */
    public function testInsertCSVError()
    {
        $file_data_names = [
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data'
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->create_table_summing_url_views();
        $this->db->enableHttpCompression(true);

        $stat = $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
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

    /**
     *
     */
    public function testSelectWhereIn()
    {
        $file_data_names = [
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data'
        ];

        $file_name_where_in1 = $this->tmp_path . '_testSelectWhereIn.1.data';
        $file_name_where_in2 = $this->tmp_path . '_testSelectWhereIn.2.data';

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->db->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');
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

        $whereIn = new \ClickHouseDB\WhereInFile();

        $whereIn->attachFile($file_name_where_in1, 'whin1', [
            'site_id'  => 'Int32',
            'url_hash' => 'String'
        ], \ClickHouseDB\WhereInFile::FORMAT_CSV);

        $whereIn->attachFile($file_name_where_in2, 'whin2', [
            'site_id'  => 'Int32',
            'url_hash' => 'String'
        ], \ClickHouseDB\WhereInFile::FORMAT_CSV);

        $result = $this->db->select('
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
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data',
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.2.data',
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.3.data'
        ];


        // --- make
        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 2);
        }

        $this->create_table_summing_url_views();
        $stat = $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);


        $st = $this->db->select('SELECT sum(views) as sum_x,min(v_00) as min_x FROM summing_url_views');
        $this->assertEquals(6408, $st->fetchOne('sum_x'));

        $st = $this->db->select('SELECT * FROM summing_url_views ORDER BY url_hash');
        $this->assertEquals(6408, $st->count());

        $st = $this->db->select('SELECT * FROM summing_url_views LIMIT 4');
        $this->assertEquals(4, $st->countAll());


        $stat = $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $st = $this->db->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');
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
        $result = $this->db->select('SELECT 12 as {key} WHERE {key} = :value', ['key' => 'ping', 'value' => 12]);
        $this->assertEquals(12, $result->fetchOne('ping'));
    }

    /**
     *
     */
    public function testSelectAsync()
    {
        $state1 = $this->db->selectAsync('SELECT 1 as {key} WHERE {key} = :value', ['key' => 'ping', 'value' => 1]);
        $state2 = $this->db->selectAsync('SELECT 2 as ping');

        $this->db->executeAsync();

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
        $this->db->enableExtremes(true);

        $state = $this->db->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');

        $this->db->enableExtremes(false);

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

    /**
     *
     */
    public function testTableExists()
    {
        $this->create_table_summing_url_views();

        $this->assertEquals('summing_url_views', $this->db->showTables()['summing_url_views']['name']);

        $this->db->write("DROP TABLE IF EXISTS summing_url_views");
    }

    /**
     * @expectedException \ClickHouseDB\DatabaseException
     */
    public function testExceptionWrite()
    {
        $this->db->write("DRAP TABLEX")->isError();
    }

    /**
     * @expectedException \ClickHouseDB\DatabaseException
     * @expectedExceptionCode 60
     */
    public function testExceptionInsert()
    {
        $this->db->insert('bla_bla', [
            ['HASH1', [11, 22, 33]],
            ['HASH1', [11, 22, 55]],
        ], ['s_key', 's_arr']);
    }

    /**
     * @expectedException \ClickHouseDB\DatabaseException
     * @expectedExceptionCode 60
     */
    public function testExceptionSelect()
    {
        $this->db->select("SELECT * FROM XXXXX_SSS")->rows();
    }

    /**
     * @expectedException \ClickHouseDB\QueryException
     * @expectedExceptionCode 6
     */
    public function testExceptionConnects()
    {
        $config = [
            'host'     => 'x',
            'port'     => '8123',
            'username' => 'x',
            'password' => 'x',
            'settings' => ['max_execution_time' => 100]
        ];

        $db = new ClickHouseDB\Client($config);
        $db->ping();
    }

    /**
     *
     */
    public function testSettings()
    {
        $config = [
            'host'     => 'x',
            'port'     => '8123',
            'username' => 'x',
            'password' => 'x',
            'settings' => ['max_execution_time' => 100]
        ];

        $db = new ClickHouseDB\Client($config);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));


        // settings via constructor
        $config = [
            'host' => 'x',
            'port' => '8123',
            'username' => 'x',
            'password' => 'x'
        ];
        $db = new ClickHouseDB\Client($config, ['max_execution_time' => 100]);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));


        //
        $config = [
            'host' => 'x',
            'port' => '8123',
            'username' => 'x',
            'password' => 'x'
        ];
        $db = new ClickHouseDB\Client($config);
        $db->settings()->set('max_execution_time', 100);
        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));


        $config = [
            'host' => 'x',
            'port' => '8123',
            'username' => 'x',
            'password' => 'x'
        ];
        $db = new ClickHouseDB\Client($config);
        $db->settings()->apply([
            'max_execution_time' => 100,
            'max_block_size' => 12345
        ]);

        $this->assertEquals(100, $db->settings()->getSetting('max_execution_time'));
        $this->assertEquals(12345, $db->settings()->getSetting('max_block_size'));
    }

    /**
     * @expectedException \ClickHouseDB\QueryException
     */
    public function testWriteEmpty()
    {
        $this->db->write('');
    }
    /**
     *
     */
    public function testInsertArrayTable()
    {
        $this->db->write("DROP TABLE IF EXISTS arrays_test_ints");
        $this->db->write('
            CREATE TABLE IF NOT EXISTS arrays_test_ints
            (
                s_key String,
                s_arr Array(UInt8)
            ) 
            ENGINE = Memory
        ');


        $state = $this->db->insert('arrays_test_ints', [
            ['HASH1', [11, 33]],
            ['HASH2', [11, 55]],
        ], ['s_key', 's_arr']);

        $this->assertGreaterThan(0, $state->totalTimeRequest());

        $state = $this->db->select('SELECT s_key, s_arr FROM arrays_test_ints ARRAY JOIN s_arr');

        $this->assertEquals(4, $state->count());
        $this->assertArraySubset([['s_key' => 'HASH1', 's_arr' => 11]], $state->rows());
    }

    /**
     * @expectedException \ClickHouseDB\QueryException
     */
    public function testInsertTableTimeout()
    {
        $this->create_table_summing_url_views();

        $file_data_names = [
            $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data',
        ];

        foreach ($file_data_names as $file_name) {
            $this->create_fake_csv_file($file_name, 5);
        }

        $this->create_table_summing_url_views();


        $this->db->setTimeout(0.01);


        $stat = $this->db->insertBatchFiles('summing_url_views', $file_data_names, [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);
        $this->db->ping();
    }
    /**
     *
     */
    public function testInsertTable()
    {
        $this->create_table_summing_url_views();

        $state = $this->insert_data_table_summing_url_views();

        $this->assertFalse($state->isError());


        $st = $this->db->select('SELECT sum(views) as sum_x, min(v_00) as min_x FROM summing_url_views');

        $this->assertEquals(122, $st->fetchOne('sum_x'));
        $this->assertEquals(9, $st->fetchOne('min_x'));
        $this->db->enableExtremes(true);
        $st = $this->db->select('SELECT * FROM summing_url_views ORDER BY url_hash');

        $this->db->enableExtremes(false);


        $this->assertEquals(4, $st->count());
        $this->assertEquals(0, $st->countAll());
        $this->assertEquals(0, sizeof($st->totals()));

        $this->assertEquals('HASH1', $st->fetchOne()['url_hash']);
        $this->assertEquals(2345, $st->extremesMin()['site_id']);

        $st = $this->db->select('
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
        $this->db->write("DROP TABLE IF EXISTS summing_url_views");
    }

    /**
     *
     */
    public function testStreamInsert()
    {
        $this->create_table_summing_url_views();

        $file_name = $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data';
        $this->create_fake_csv_file($file_name, 1);

        $source = fopen($file_name, 'rb');
        $request = $this->db->insertBatchStream('summing_url_views', [
            'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
        ]);

        $curlerRolling = new \Curler\CurlerRolling();
        $streamInsert = new ClickHouseDB\Transport\StreamInsert($source, $request, $curlerRolling);

        $callable = function ($ch, $fd, $length) use ($source) {
            return ($line = fread($source, $length)) ? $line : '';
        };
        $streamInsert->insert($callable);

        // check the resource was close after insert method
        $this->assertEquals(false, is_resource($source));

        $statement = $this->db->select('SELECT * FROM summing_url_views');
        $this->assertEquals(count(file($file_name)), $statement->count());
    }

    /**
     *
     */
    public function testStreamInsertExeption()
    {
        $file_name = $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data';
        $this->create_fake_csv_file($file_name, 1);

        $source = fopen($file_name, 'rb');
        $curlerRolling = new \Curler\CurlerRolling();
        $streamInsert = new ClickHouseDB\Transport\StreamInsert($source, new \Curler\Request(), $curlerRolling);

        $this->expectException(InvalidArgumentException::class);
        $streamInsert->insert([]);
    }

    /**
     *
     */
    public function testStreamInsertExceptionResourceIsClose()
    {
        $file_name = $this->tmp_path . '_testInsertCSV_clickHouseDB_test.1.data';
        $this->create_fake_csv_file($file_name, 1);

        $source = fopen($file_name, 'rb');
        $curlerRolling = new \Curler\CurlerRolling();
        $streamInsert = new ClickHouseDB\Transport\StreamInsert($source, new \Curler\Request(), $curlerRolling);
        try {
            $streamInsert->insert([]);
        } catch (\Exception $e) {}

        // check the resource was close after insert method
        $this->assertEquals(false, is_resource($source));
    }
}
