<?php
include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';


echo "\nPrepare....\n";
$client = new ClickHouseDB\Client($config);
$client->write('DROP TABLE IF EXISTS _phpCh_SteamTest');

$client->write('CREATE TABLE _phpCh_SteamTest (a Int32) Engine=Log');




$stream = fopen('php://memory','r+');
for($f=0;$f<121123;$f++)
fwrite($stream, json_encode(['a'=>$f]).PHP_EOL );
rewind($stream);

echo "\nstreamWrite....\n";

stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ, ['window' => 30]);


$r=$client->streamWrite(
        $stream,
        'INSERT INTO {table_name} FORMAT JSONEachRow',
        ['table_name'=>'_phpCh_SteamTest'],
        null
        ,
        true
);

print_r($r->info_upload());
//stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ, ["window" => 30]);


print_r($client->select('SELECT sum(a) FROM _phpCh_SteamTest')->rows());




// ------------------------