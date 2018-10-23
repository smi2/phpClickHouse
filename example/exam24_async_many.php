<?php
include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';


echo "\nPrepare....\n";
$client = new ClickHouseDB\Client($config);
$client->ping();
echo "OK!\n";

$counter=rand(150,400);
$list=[];
for ($f=0;$f<$counter;$f++)
{
    $list[$f]=$client->selectAsync('SELECT {num} as num,sleep(0.9),SHA256(\'123{num}\') as s',['num'=>$f]);
}
$client->executeAsync();
for ($f=0;$f<$counter;$f++)
{
    $ResultInt=$list[$f]->fetchOne('num');
    if ($ResultInt !== $f) {
        echo "ERROR:$f\n";
    }
}
