<?php
use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{
    /**
     * @var \ClickHouse\Client
     */
    private $db;
    private $tablename = 'all_types_test_table';
    public function setUp()
    {
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
    }
    public function tearDown()
    {
        //
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
    public function testInsertTable()
    {
        $this->db->write("DROP TABLE IF EXISTS test_summing_url_views");
        $this->db->write(
            '
CREATE TABLE IF NOT EXISTS test_summing_url_views (
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

        $stat=$this->db->insert('test_summing_url_views',
            [
                [time(),'HASH1',2345,22,20,2],
                [time(),'HASH2',2345,12,9,3],
                [time(),'HASH3',5345,33,33,0],
                [time(),'HASH3',5345,55,12,55],
            ]
            ,
            ['event_time','url_hash','site_id','views','v_00','v_55']
        );


        $st=$this->db->select('SELECT sum(views) as sum_x,min(v_00) as min_x FROM test_summing_url_views');

        $this->assertEquals(122, $st->fetchOne('sum_x'));
        $this->assertEquals(9, $st->fetchOne('min_x'));

        $this->db->write("DROP TABLE IF EXISTS test_summing_url_views");




    }
}
