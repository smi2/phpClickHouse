<?php
include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';



$db = new ClickHouseDB\Client($config);


$db->enableExtremes(true)->enableHttpCompression();

$db->write("DROP TABLE IF EXISTS xxxx");
$db->write('
    CREATE TABLE IF NOT EXISTS xxxx (
        event_date Date,
        url_hash String,
        site_id Int32,
        views Int32
    ) 
    ENGINE = SummingMergeTree(event_date, (site_id, url_hash), 8192)
');

// ARRAY TO TABLE

$rows=[
    ['2017-01-01','XXXXX',123,1],
    ['2017-01-02','XXXXX',123,1],
    ['2017-01-03','XXXXX',123,1],
    ['2017-01-04','XXXXX',123,1],
    ['2017-01-05','XXXXX',123,1],
    ['2017-01-06','XXXXX',123,1],
    ['2017-01-07','XXXXX',123,1]
];



// Write to file array
$temp_file_name='/tmp/_test_data.TSV';


if (file_exists($temp_file_name)) unlink('/tmp/_test_data.TSV');
foreach ($rows as $row)
{

    file_put_contents($temp_file_name,\ClickHouseDB\Quote\FormatLine::TSV($row)."\n",FILE_APPEND);

}

echo "CONTENT FILES:\n";
echo file_get_contents($temp_file_name);
echo "------\n";

//
$db->insertBatchTSVFiles('xxxx', [$temp_file_name], [
    'event_date',
    'url_hash',
    'site_id',
    'views'
]);



print_r($db->select('SELECT * FROM xxxx')->rows());



/**
CONTENT FILES:
2017-01-01	XXXXX	123	1
2017-01-02	XXXXX	123	1
2017-01-03	XXXXX	123	1
2017-01-04	XXXXX	123	1
2017-01-05	XXXXX	123	1
2017-01-06	XXXXX	123	1
2017-01-07	XXXXX	123	1
------
Array
(
[0] => Array
(
[event_date] => 2017-01-01
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

[1] => Array
(
[event_date] => 2017-01-02
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

[2] => Array
(
[event_date] => 2017-01-03
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

[3] => Array
(
[event_date] => 2017-01-04
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

[4] => Array
(
[event_date] => 2017-01-05
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

[5] => Array
(
[event_date] => 2017-01-06
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

[6] => Array
(
[event_date] => 2017-01-07
[url_hash] => XXXXX
[site_id] => 123
[views] => 1
)

)
 *
 */
