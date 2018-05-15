<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();




$config = include_once __DIR__ . '/00_config_connect.php';


$start_time = microtime(true);

$db = new ClickHouseDB\Client($config);


// some file names to data
$file_name_data1 = "/tmp/temp_csv.txt";
$file_name_data2 = "/tmp/site_keys.data";

// create CSV file
\ClickHouseDB\Example\Helper::makeListSitesKeysDataFile($file_name_data1, 1000, 2000); // see lib_example.php
\ClickHouseDB\Example\Helper::makeListSitesKeysDataFile($file_name_data2, 5000, 6000); // see lib_example.php


// create WhereInFile
$whereIn = new \ClickHouseDB\Query\WhereInFile();


// attachFile( {full_file_path} , {data_table_name} , [ { structure } ]

$whereIn->attachFile($file_name_data1, 'namex', ['site_id' => 'Int32', 'site_hash' => 'String'], \ClickHouseDB\Query\WhereInFile::FORMAT_CSV);
$whereIn->attachFile($file_name_data2, 'site_keys', ['site_id' => 'Int32', 'site_hash' => 'String'], \ClickHouseDB\Query\WhereInFile::FORMAT_CSV);

$result = $db->select('select 1', [], $whereIn);
print_r($result->rows());

// ----------------------------------------------- ASYNC ------------------------------------------------------------------------------------------
echo "\n----------------------- ASYNC ------------ \n";


$bindings['limit'] = 3;

$statements = [];
$whereIn = new \ClickHouseDB\Query\WhereInFile();
$whereIn->attachFile($file_name_data1, 'namex', ['site_id' => 'Int32', 'site_hash' => 'String'], \ClickHouseDB\Query\WhereInFile::FORMAT_CSV);

$statements[0] = $db->selectAsync('select 3', $bindings, $whereIn);


// change data file - for statement two
$whereIn = new \ClickHouseDB\Query\WhereInFile();
$whereIn->attachFile($file_name_data2, 'namex', ['site_id' => 'Int32', 'site_hash' => 'String'], \ClickHouseDB\Query\WhereInFile::FORMAT_CSV);

$statements[1] = $db->selectAsync('select 2', $bindings, $whereIn);
$db->executeAsync();


foreach ($statements as $statement) {
    print_r($statement->rows());
}

