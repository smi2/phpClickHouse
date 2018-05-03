<?php

include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);


// ---------------------------- Write ----------------------------
echo "\n-----\ntry write:create_table\n";
$db->database('default');
//------------------------------------------------------------------------------

echo 'Tables EXISTS: ' . json_encode($db->showTables()) . PHP_EOL;
$db->write('DROP TABLE IF EXISTS summing_url_views');
echo 'Tables EXISTS: ' . json_encode($db->showTables()) . PHP_EOL;

$db->write('
    CREATE TABLE IF NOT EXISTS summing_url_views (
        event_date Date DEFAULT toDate(event_time),
        event_time DateTime,
        url_hash String,
        site_id Int32,
        views Int32,
        v_00 Int32,
        v_55 Int32
    ) 
    ENGINE = SummingMergeTree(event_date, (site_id, url_hash, event_time, event_date), 8192)
'
);
echo 'Table EXISTS: ' . json_encode($db->showTables()) . PHP_EOL;

/*
Table EXISTS: [{"name": "summing_url_views"}]
*/

//------------------------------------------------------------------------------
echo "Insert\n";

$stat = $db->insert('summing_url_views',
    [
        [time(), 'HASH1', 2345, 22, 20, 2],
        [time(), 'HASH2', 2345, 12, 9, 3],
        [time(), 'HASH3', 5345, 33, 33, 0],
        [time(), 'HASH3', 5345, 55, 0, 55],
    ],
    ['event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55']
);

echo "Insert Done\n";
//------------------------------------------------------------------------------



echo "Try select \n";


$st = $db->select('SELECT * FROM summing_url_views LIMIT 2');




echo "Count select rows:".$st->count()."\n";
echo "Count all rows:".$st->countAll()."\n";
echo "First row:\n";
print_r($st->fetchOne());

echo "extremes_min:\n";
print_r($st->extremesMin());

echo "totals:\n";
print_r($st->totals());



$st=$db->select('SELECT event_date,url_hash,sum(views),avg(views) FROM summing_url_views WHERE site_id<3333 GROUP BY event_date,url_hash WITH TOTALS');




echo "Count select rows:".$st->count()."\n";
/*
2
 */
echo "Count all rows:".$st->countAll()."\n";
/*
false
 */



echo "First row:\n";
print_r($st->fetchOne());
/*
(
    [event_date] => 2016-07-18
    [url_hash] => HASH1
    [sum(views)] => 22
    [avg(views)] => 22
)
 */


echo "totals:\n";
print_r($st->totals());
/*
(
    [event_date] => 0000-00-00
    [url_hash] =>
    [sum(views)] => 34
    [avg(views)] => 17
)

 */


echo "Tree Path [event_date.url_hash]:\n";
print_r($st->rowsAsTree('event_date.url_hash'));
/*
(
    [2016-07-18] => Array
        (
            [HASH2] => Array
                (
                    [event_date] => 2016-07-18
                    [url_hash] => HASH2
                    [sum(views)] => 12
                    [avg(views)] => 12
                )
            [HASH1] => Array
                (
                    [event_date] => 2016-07-18
                    [url_hash] => HASH1
                    [sum(views)] => 22
                    [avg(views)] => 22
                )
        )
)
 */
$db->write("DROP TABLE IF EXISTS summing_url_views");
echo "Tables EXISTS:".json_encode($db->showTables())."\n";
/*
Tables EXISTS:[]
 */