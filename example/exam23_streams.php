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


$streamWrite=new ClickHouseDB\Transport\StreamWrite($stream);
$streamWrite->applyGzip();

$callable = function ($ch, $fd, $length) use ($stream) {
    return ($line = fread($stream, $length)) ? $line : '';
};


$streamWrite->closure($callable);

$r=$client->streamWrite($streamWrite,'INSERT INTO {table_name} FORMAT JSONEachRow', ['table_name'=>'_phpCh_SteamTest']);

print_r($r->info_upload());
//print_r($client->select('SELECT sum(a) FROM _phpCh_SteamTest')->rows());


// ------------------------------------------------------------------------------------------------------------------------
$streamWrite=new ClickHouseDB\Transport\StreamWrite($stream);
$streamWrite->applyDeflate();
$callable = function ($ch, $fd, $length) use ($stream) {
    return ($line = fread($stream, $length)) ? $line : '';
};
$streamWrite->closure($callable);




// ------------------------------------------------------------------------------------------------------------------------