<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/lib_example.php';

$config = [
    'host' => '192.168.1.20',
    'port' => '8123',
    'username' => 'default',
    'password' => ''
];


$db = new ClickHouseDB\Client($config);
$_flag_create_table=false;


$size=$db->tableSize('summing_url_views_big');
echo "Site table summing_url_views_big : ".(isset($size['size'])?$size['size']:'false')."\n";


if (!isset($size['size'])) $_flag_create_table=true;


if ($_flag_create_table) {


    $db->write("DROP TABLE IF EXISTS summing_url_views_big");
    $db->write('
                CREATE TABLE IF NOT EXISTS summing_url_views_big (
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
        '/tmp/clickHouseDB_test.big.1.data',
        '/tmp/clickHouseDB_test.big.2.data',
        '/tmp/clickHouseDB_test.big.3.data',
    ];

    $c = 0;
    foreach ($file_data_names as $file_name) {
        $c++;
        makeSomeDataFileBig($file_name, 40 * $c);
    }

    echo "----------------------------------------------------------------------------------------------------\n";
    echo "insert ALL file async + GZIP:\n";

    $db->enableHttpCompression(true);
    $time_start = microtime(true);

    $result_insert = $db->insertBatchFiles('summing_url_views_big', $file_data_names, [
        'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
    ]);

    echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";

    foreach ($result_insert as $fileName => $state) {
        echo "$fileName => " . json_encode($state->info_upload()) . "\n";
    }

    print_r($db->select('select sum(views) from summing_url_views_big')->rows());


    echo "----------------------------------------------------------------------------------------------------\n";
}
echo "php_ini.memory_limit = ".ini_get("memory_limit")."\n";
ini_set("memory_limit","1256M");
echo "php_ini.memory_limit = ".ini_get("memory_limit")."\n";




memoryUsage::show();
$sql=('select * from summing_url_views_big LIMIT 50000');

echo ">>> $sql\n";

$db->select($sql);


memoryUsage::show();

$rows=($db->select($sql)->rows());

memoryUsage::show('select rows');

unset($rows);

memoryUsage::show('unset rows ');

$rows=($db->select($sql)->rawData());


memoryUsage::show('rawData');

unset($rows);

memoryUsage::show('unset rows ');


$rows=($db->select($sql)->rawData(true));


memoryUsage::show('rawData');

unset($rows);

memoryUsage::show('unset rows ');



memoryUsage::showPeak();