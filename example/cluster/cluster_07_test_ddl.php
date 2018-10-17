<?php

include_once __DIR__ . '/../../include.php';
include_once __DIR__ . '/../Helper.php';
\ClickHouseDB\Example\Helper::init();
// load production config
$config = include_once __DIR__ . '/00_config_connect.php';



$cl = new ClickHouseDB\Cluster(['host'=>'172.18.0.8','username'=>'default','password'=>'','port'=>8123]);

$cl->setScanTimeOut(2.5); // 2500 ms
$cl->setSoftCheck(true);
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}

print_r($cl->getClusterList());


print_r($cl->getNodes());

print_r($cl->getClusterNodes('cluster'));


$cl->activeClient()->setTimeout(0.01);
for ($z=0;$z<50;$z++)
{
    try{
        $x=$cl->activeClient()->write("DROP TABLE IF EXISTS default.asdasdasd ON CLUSTER cluster2");
    }catch (Exception $exception)
    {

    }
}

$cl->activeClient()->setTimeout(22);
$x=$cl->activeClient()->write("DROP TABLE IF EXISTS default.asdasdasd ON CLUSTER cluster2");
$x->dump();



echo "\n----\nEND\n";
// ----------------------------------------------------------------------

