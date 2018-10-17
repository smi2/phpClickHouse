<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);


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

echo "Table EXISTS:" . json_encode($db->showTables()) . "\n";
// ------------------------------------------------------------------------------------------------------

echo "----------------------------------- CREATE big csv file -----------------------------------------------------------------\n";


$file_data_names = [
    '/tmp/clickHouseDB_test.b.1.data',
    '/tmp/clickHouseDB_test.b.2.data',
    '/tmp/clickHouseDB_test.b.3.data',
    '/tmp/clickHouseDB_test.b.4.data',
    '/tmp/clickHouseDB_test.b.5.data',
];

$c = 0;
foreach ($file_data_names as $file_name) {
    $c++;
    \ClickHouseDB\Example\Helper::makeSomeDataFileBig($file_name, 40 * $c);
}

echo "----------------------------------------------------------------------------------------------------\n";
echo "insert ALL file async NO gzip:\n";


$db->settings()->max_execution_time(200);
$time_start = microtime(true);

$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [
    'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
]);

echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";

foreach ($result_insert as $state) {
    echo "Info : " . json_encode($state->info_upload()) . "\n";
}

print_r($db->select('select sum(views) from summing_url_views')->rows());


echo "--------------------------------------- enableHttpCompression -------------------------------------------------------------\n";
echo "insert ALL file async + GZIP:\n";

$db->enableHttpCompression(true);
$time_start = microtime(true);

$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [
    'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
]);

echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";

foreach ($result_insert as $fileName => $state) {
    echo "$fileName => " . json_encode($state->info_upload()) . "\n";
}

print_r($db->select('select sum(views) from summing_url_views')->rows());


echo "----------------------------------------------------------------------------------------------------\n";
echo ">>> rm -f /tmp/clickHouseDB_test.b.*\n";

/*


Table EXISTSs:[{"name":"summing_url_views"}]
----------------------------------- CREATE big csv file -----------------------------------------------------------------
Created file  [/tmp/clickHouseDB_test.b.1.data]: 177600 rows... size = 25.74 MB
Created file  [/tmp/clickHouseDB_test.b.2.data]: 355200 rows... size = 51.49 MB
Created file  [/tmp/clickHouseDB_test.b.3.data]: 532800 rows... size = 77.23 MB
Created file  [/tmp/clickHouseDB_test.b.4.data]: 710400 rows... size = 102.98 MB
Created file  [/tmp/clickHouseDB_test.b.5.data]: 888000 rows... size = 128.72 MB
----------------------------------------------------------------------------------------------------
insert ALL file async NO gzip:
use time:100.94
Info : {"size_upload":"25.74 MB","upload_content":"25.74 MB","speed_upload":"10.11 Mbps","time_request":21.358527}
Info : {"size_upload":"51.49 MB","upload_content":"51.49 MB","speed_upload":"10.67 Mbps","time_request":40.490685}
Info : {"size_upload":"77.23 MB","upload_content":"77.23 MB","speed_upload":"10.52 Mbps","time_request":61.610698}
Info : {"size_upload":"102.98 MB","upload_content":"102.98 MB","speed_upload":"10.8 Mbps","time_request":80.016749}
Info : {"size_upload":"128.72 MB","upload_content":"128.72 MB","speed_upload":"10.7 Mbps","time_request":100.931881}
Array
(
    [0] => Array
        (
            [sum(views)] => 2664000
        )

)
--------------------------------------- enableHttpCompression -------------------------------------------------------------
insert ALL file async + GZIP:
use time:34.76
/tmp/clickHouseDB_test.b.1.data => {"size_upload":"5.27 MB","upload_content":"-1 bytes","speed_upload":"5.23 Mbps","time_request":8.444056}
/tmp/clickHouseDB_test.b.2.data => {"size_upload":"10.54 MB","upload_content":"-1 bytes","speed_upload":"5.53 Mbps","time_request":15.974618}
/tmp/clickHouseDB_test.b.3.data => {"size_upload":"15.80 MB","upload_content":"-1 bytes","speed_upload":"4.98 Mbps","time_request":26.64583}
/tmp/clickHouseDB_test.b.4.data => {"size_upload":"21.07 MB","upload_content":"-1 bytes","speed_upload":"6.3 Mbps","time_request":28.05784}
/tmp/clickHouseDB_test.b.5.data => {"size_upload":"26.34 MB","upload_content":"-1 bytes","speed_upload":"6.36 Mbps","time_request":34.738461}
Array
(
    [0] => Array
        (
            [sum(views)] => 5328000
        )

)
----------------------------------------------------------------------------------------------------



 */
