<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();




$config = include_once __DIR__ . '/00_config_connect.php';

$db = new ClickHouseDB\Client($config);
$db->enableHttpCompression(true);

$db->write("DROP TABLE IF EXISTS summing_url_views");
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
');

echo "Table EXISTS: " . json_encode($db->showTables()) . "\n";

// --------------------------------  CREATE csv file ----------------------------------------------------------------


// ----------------------------------------------------------------------------------------------------


$file_data_names = [
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
    '/tmp/clickHouseDB_test.3.data',
    '/tmp/clickHouseDB_test.4.data',
    '/tmp/clickHouseDB_test.5.data',
];

foreach ($file_data_names as $file_name) {
    \ClickHouseDB\Example\Helper::makeSomeDataFile($file_name, 5);
}

// ----------------------------------------------------------------------------------------------------
echo "insert ONE file:\n";

$time_start = microtime(true);

$stat = $db->insertBatchFiles('summing_url_views', ['/tmp/clickHouseDB_test.1.data'], [
    'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
]);

echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";

print_r($db->select('select sum(views) from summing_url_views')->rows());

echo "insert ALL file async:\n";

$time_start = microtime(true);
$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [
    'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
]);

echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";


print_r($db->select('select sum(views) from summing_url_views')->rows());

// ------------------------------------------------------------------------------------------------
foreach ($file_data_names as $fileName) {
    echo $fileName . " : " . $result_insert[$fileName]->totalTimeRequest() . "\n";
}
// ------------------------------------------------------------------------------------------------

/*
Table EXISTSs:[{"name":"summing_url_views"}]
Created file  [/tmp/clickHouseDB_test.1.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.2.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.3.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.4.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.5.data]: 22200 rows...
insert ONE file:
use time:0.7
Array
(
    [0] => Array
        (
            [sum(views)] => 22200
        )

)
insert ALL file async:
use time:0.74
Array
(
    [0] => Array
        (
            [sum(views)] => 133200
        )

)
*/