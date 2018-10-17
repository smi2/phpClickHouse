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

echo $db->showCreateTable('summing_url_views');

// --------------------------------  CREATE csv file ----------------------------------------------------------------


$file_data_names = [
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
];

foreach ($file_data_names as $file_name) {
    \ClickHouseDB\Example\Helper::makeSomeDataFile($file_name, 2);
}

// ----------------------------------------------------------------------------------------------------

echo "insert ALL file async:\n";

$time_start = microtime(true);
$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [
    'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
]);

echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";
print_r($db->select('select sum(views) from summing_url_views')->rows());
// ------------------------------------------------------------------------------------------------
$WriteToFile=new ClickHouseDB\Query\WriteToFile('/tmp/_1_select.csv');
$statement=$db->select('select * from summing_url_views',[],null,$WriteToFile);
print_r($statement->info());

//
$db->selectAsync('select * from summing_url_views limit 4',[],null,new ClickHouseDB\Query\WriteToFile('/tmp/_2_select.csv'));
$db->selectAsync('select * from summing_url_views limit 4',[],null,new ClickHouseDB\Query\WriteToFile('/tmp/_3_select.tab',true,'TabSeparatedWithNames'));
$db->selectAsync('select * from summing_url_views limit 4',[],null,new ClickHouseDB\Query\WriteToFile('/tmp/_4_select.tab',true,'TabSeparated'));
$statement=$db->selectAsync('select * from summing_url_views limit 54',[],null,new ClickHouseDB\Query\WriteToFile('/tmp/_5_select.csv',true,ClickHouseDB\Query\WriteToFile::FORMAT_CSV));
$db->executeAsync();

print_r($statement->info());
echo "END SELECT\n";


echo "TRY GZIP\n";

$WriteToFile=new ClickHouseDB\Query\WriteToFile('/tmp/_0_select.csv.gz');
$WriteToFile->setFormat(ClickHouseDB\Query\WriteToFile::FORMAT_TabSeparatedWithNames);
$WriteToFile->setGzip(true);// cat /tmp/_0_select.csv.gz | gzip -dc > /tmp/w.result

$statement=$db->select('select * from summing_url_views',[],null,$WriteToFile);
print_r($statement->info());

echo "OK!\n\n";