<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/lib_example.php';




//$config = [
//    'host' => '192.168.1.20',
//    'port' => '8123',
//    'username' => 'default',
//    'password' => ''
//];
//

// load production config
$config = include_once __DIR__ . '/../../_clickhouse_config_product.php';

// ----------------------------------------------------------------------
$cluster_name='ads';
$cl = new ClickHouseDB\Cluster($config);
$cl->setScanTimeOut(0.45); // 200 ms

if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad');
}

$sql=[
'up'=>['CREATE DATABASE IF NOT EXISTS ttests'],
'down'=>['DROP DATABASE IF EXISTS ttests ']
];
echo "getClusterList:\n";
print_r($cl->getClusterList());

echo "getClusterHosts:model:\n";
print_r($cl->getClusterHosts('model'));





echo "\n----\nEND\n";

// ----------------------------------------------------------------------
