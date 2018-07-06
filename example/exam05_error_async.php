<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();


$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);


for ($f=0;$f<1000;$f++)
{
    $list[$f]=$db->selectAsync('SELECT {num} as num',['num'=>$f]);
}
$db->executeAsync();
for ($f=0;$f<1000;$f++)
{
    $c=$list[$f];

    echo $f."\t";
    $ret='-';
    try{
        $ret=$c->fetchOne('num');
    }catch (Exception $e)
    {

    }


    echo "$ret\n";
}

// -------------------------------- ------- ----------------------------------------------------------------




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

echo "Table EXISTSs:" . json_encode($db->showTables()) . "\n";

// --------------------------------  CREATE csv file ----------------------------------------------------------------
$file_data_names = [
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
];

foreach ($file_data_names as $file_name) {
    \ClickHouseDB\Example\Helper::makeSomeDataFile($file_name, 1);
}
// ----------------------------------------------------------------------------------------------------

echo "insert ONE file:\n";

$time_start = microtime(true);
$version_test = 3;

if ($version_test == 1) {
    $statselect1 = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
    $statselect2 = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');

    $stat = $db->insertBatchFiles('summing_url_views', ['/tmp/clickHouseDB_test.1.data'], [
        'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
    ]);

    // 'Exception' with message 'Queue must be empty, before insertBatch,need executeAsync'
}

//
if ($version_test == 2) {
    $statselect1 = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
    print_r($statselect1->rows());
    // 'Exception' with message 'Not have response'
}

// good
if ($version_test == 3) {
    $statselect2 = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
    $db->executeAsync();

    $stat = $db->insertBatchFiles('summing_url_views', ['/tmp/clickHouseDB_test.1.data'], [
        'event_time', 'url_hash', 'site_id', 'views', 'v_00',  'v_55'
    ]);

    $statselect1 = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
    $db->executeAsync();

    print_r($statselect1->rows());
}



