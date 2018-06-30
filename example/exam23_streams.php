<?php
include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';



$stream = fopen('php://memory','r+');
for($f=0;$f<10000;$f++)
fwrite($stream, json_encode(['a'=>$f]).PHP_EOL );
rewind($stream);


$client = new ClickHouseDB\Client($config);
$client->write('DROP TABLE IF EXISTS _phpCh_SteamTest');

$client->write('CREATE TABLE _phpCh_SteamTest (a Int32) Engine=Log');

echo "\nstreamWrite....\n";
$client->streamWrite($stream,'INSERT INTO {table_name} FORMAT JSONEachRow',['table_name'=>'_phpCh_SteamTest']);

print_r($client->select('SELECT * FROM _phpCh_SteamTest')->rows());






// ------------------------