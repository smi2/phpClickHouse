<?php
include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';


echo "\nPrepare....\n";
$client = new ClickHouseDB\Client($config);
$client->write('DROP TABLE IF EXISTS _phpCh_SteamTest');



$client->write('CREATE TABLE _phpCh_SteamTest (a Int32) Engine=Log');





echo "\n\n------------------------------------ 0 ---------------------------------------------------------------------------------\n\n";




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


print_r($client->select("SELECT sum(a) as s FROM _phpCh_SteamTest ")->fetchOne('s'));

echo "\n\n------------------------------------ 1 ---------------------------------------------------------------------------------\n\n";


$stream = fopen('php://memory','r+');

$streamRead=new ClickHouseDB\Transport\StreamRead($stream);

$r=$client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 4 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);
rewind($stream);
while (($buffer = fgets($stream, 4096)) !== false) {
    echo ">>> ".$buffer;
}
fclose($stream);



echo "\n\n---------------------------------- 2 --------------------------------------------------------------------------------------\n\n";



$stream = fopen('php://memory','r+');
$streamRead=new ClickHouseDB\Transport\StreamRead($stream);
$callable = function ($ch, $string) use ($stream) {
    // some magic for _BLOCK_ data
    fwrite($stream, str_ireplace('"sin"','"max"',$string));
    return strlen($string);
};

$streamRead->closure($callable);

$r=$client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 44 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);

echo "size_download:".($r->info()['size_download'])."\n";



rewind($stream);



while (($buffer = fgets($stream, 4096)) !== false) {
    echo "".$buffer;
}
fclose($stream);
// ------------------------------------------------------------------------------------------------------------------------






