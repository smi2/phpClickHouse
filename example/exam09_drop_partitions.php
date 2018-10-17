<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);
$create = true;

if ($create) {
    $db->write("DROP TABLE IF EXISTS summing_partions_views");
    $db->write('
        CREATE TABLE IF NOT EXISTS summing_partions_views (
            event_date Date DEFAULT toDate(event_time),
            event_time DateTime,
            site_id Int32,
            hash_id Int32,
            views Int32
        ) 
        ENGINE = SummingMergeTree(event_date, (site_id,hash_id, event_time, event_date), 8192)
    ');

    echo "Table EXISTS:" . json_encode($db->showTables()) . "\n";
    echo "----------------------------------- CREATE csv file -----------------------------------------------------------------\n";


    $file_data_names = [
        '/tmp/clickHouseDB_test.part.1.data',
        '/tmp/clickHouseDB_test.part.2.data',
        '/tmp/clickHouseDB_test.part.3.data',
    ];

    $c = 0;
    foreach ($file_data_names as $file_name) {
        $c++;
        \ClickHouseDB\Example\Helper::makeSomeDataFileBigOldDates($file_name, $c);
    }


    echo "--------------------------------------- insert -------------------------------------------------------------\n";
    echo "insert ALL file async + GZIP:\n";

    $db->enableHttpCompression(true);
    $time_start = microtime(true);

    $result_insert = $db->insertBatchFiles('summing_partions_views', $file_data_names, [
        'event_time', 'site_id', 'hash_id', 'views'
    ]);

    echo "use time:" . round(microtime(true) - $time_start, 2) . " sec.\n";

    foreach ($result_insert as $fileName => $state) {
        echo "$fileName => " . json_encode($state->info_upload()) . "\n";
    }
}


echo "--------------------------------------- select -------------------------------------------------------------\n";

print_r($db->select('select min(event_date),max(event_date) from summing_partions_views ')->rows());

echo "--------------------------------------- list partitions -------------------------------------------------------------\n";

echo "databaseSize : " . json_encode($db->databaseSize()) . "\n";
echo "tableSize    : " . json_encode($db->tableSize('summing_partions_views')) . "\n";
echo "partitions    : " . json_encode($db->partitions('summing_partions_views', 2)) . "\n";


echo "--------------------------------------- drop partitions -------------------------------------------------------------\n";

echo "dropOldPartitions -30 days    : " . json_encode($db->dropOldPartitions('summing_partions_views', 30)) . "\n";

echo "--------------------------------------- list partitions -------------------------------------------------------------\n";

echo "databaseSize : " . json_encode($db->databaseSize()) . "\n";
echo "tableSize    : " . json_encode($db->tableSize('summing_partions_views')) . "\n";
echo "partitions    : " . json_encode($db->partitions('summing_partions_views', 2)) . "\n";

