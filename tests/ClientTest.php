<?php
use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{
    /**
     * @var \ClickHouseDB\Client
     */
    private $db;
    private $tablename = 'all_types_test_table';
    public function setUp()
    {
        date_default_timezone_set('Europe/Moscow');

        if (!defined('phpunit_clickhouse_host')) {
            throw new Exception("Not set phpunit_clickhouse_host in phpUnit.xml");
        }
        $config=[
                'host'=>phpunit_clickhouse_host,
                'port'=>phpunit_clickhouse_port,
                'username'=>phpunit_clickhouse_user,
                'password'=>phpunit_clickhouse_pass
        ];
        $this->db = new ClickHouseDB\Client($config);
        $this->db->ping();
    }
    public function tearDown()
    {
        //
    }

    private function insert_data_table_summing_url_views()
    {
        return $this->db->insert('summing_url_views',
            [
                [strtotime('2010-10-10 00:00:00'),'HASH1',2345,22,20,2],
                [strtotime('2010-10-11 01:00:00'),'HASH2',2345,12,9,3],
                [strtotime('2010-10-12 02:00:00'),'HASH3',5345,33,33,0],
                [strtotime('2010-10-13 03:00:00'),'HASH4',5345,55,12,55],
            ]
            ,
            ['event_time','url_hash','site_id','views','v_00','v_55']
        );
    }

    /**
     * @return \ClickHouseDB\Statement
     */
    private function create_table_summing_url_views()
    {
        $this->db->write("DROP TABLE IF EXISTS summing_url_views");
        return $this->db->write(
            '
CREATE TABLE IF NOT EXISTS summing_url_views (
event_date Date DEFAULT toDate(event_time),
event_time DateTime,
url_hash String,
site_id Int32,
views Int32,
v_00 Int32,
v_55 Int32
) ENGINE = SummingMergeTree(event_date, (site_id, url_hash, event_time, event_date), 8192)
'
        );

    }


    public function testPing()
    {
        $result=$this->db->select('SELECT 12 as {key} WHERE {key}=:value',['key'=>'ping','value'=>12]);
        $this->assertEquals(12, $result->fetchOne('ping'));
    }



    public function testSelectAsync()
    {
        $state1=$this->db->selectAsync('SELECT 1 as {key} WHERE {key}=:value',['key'=>'ping','value'=>1]);
        $state2=$this->db->selectAsync('SELECT 2 as ping');

        $this->db->executeAsync();

        $this->assertEquals(1, $state1->fetchOne('ping'));
        $this->assertEquals(2, $state2->fetchOne('ping'));

    }
    public function testTableExists()
    {
        $this->create_table_summing_url_views();

        $this->assertEquals('summing_url_views',$this->db->showTables()['summing_url_views']['name']);

        $this->db->write("DROP TABLE IF EXISTS summing_url_views");
    }
    public function testInsertTable()
    {
        
        $this->create_table_summing_url_views();

        $state=$this->insert_data_table_summing_url_views();

        $this->assertFalse($state->isError());


        $st=$this->db->select('SELECT sum(views) as sum_x,min(v_00) as min_x FROM summing_url_views');

        $this->assertEquals(122, $st->fetchOne('sum_x'));
        $this->assertEquals(9, $st->fetchOne('min_x'));

        $st=$this->db->select('SELECT * FROM summing_url_views ORDER BY url_hash');


        $this->assertEquals(4, $st->count());
        $this->assertEquals(0, $st->countAll());
        $this->assertEquals(0, sizeof($st->totals() ));

        $this->assertEquals('HASH1',$st->fetchOne()['url_hash']);
        $this->assertEquals(2345,$st->extremesMin()['site_id']);

        $st=$this->db->select('SELECT url_hash,sum(views) as vv,avg(views) as avgv FROM summing_url_views WHERE site_id<3333 GROUP BY url_hash WITH TOTALS');


        $this->assertEquals(2, $st->count());
        $this->assertEquals(0, $st->countAll());

        $this->assertEquals(34, $st->totals()['vv']);
        $this->assertEquals(17, $st->totals()['avgv']);



        $this->assertEquals(22,$st->rowsAsTree('url_hash')['HASH1']['vv']);
        // drop

        $this->db->write("DROP TABLE IF EXISTS summing_url_views");





    }
}
