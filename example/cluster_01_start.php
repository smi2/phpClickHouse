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
$cl = new ClickHouseDB\Cluster($config);



$sql_up=['CREATE DATABASE IF NOT EXISTS cluster_tests',

'
CREATE TABLE IF NOT EXISTS cluster_tests.sum_views_sharded (
    event_date Date DEFAULT toDate(event_time),
    event_time DateTime DEFAULT now(),
    site_id Int32,
    views Int32,
    bad_views Int32
) ENGINE = ReplicatedSummingMergeTree(\'/clickhouse/tables/{aggr_replica}/aggr/sum_views_sharded\', \'{replica}\', event_date, (event_date,event_time,site_id), 8192)
',
'
CREATE TABLE IF NOT EXISTS cluster_tests.sum_views AS cluster_tests.sum_views_sharded ENGINE = Distributed(aggr, cluster_tests, sum_views_sharded, rand())
'
];
$sql_down=[
        'DROP DATABASE IF EXISTS cluster_tests',
        'DROP TABLE IF EXISTS cluster_tests.sum_views_sharded',
        'DROP TABLE IF EXISTS cluster_tests.sum_views',
];

if (!$cl->createCluster($sql_up,$sql_down))
{
    echo "createCluster:false , ".$cl->getError()."\n";
    exit;
}
echo "createCluster:OK!\n";

// ----------------------------------------------------------------------
$cl = new ClickHouseDB\Cluster($config);

$cl->setScanTimeOut(2.5); // 2500 ms
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}

echo "Ips:\n";
print_r($cl->getIps());
echo "getClusterList:\n";
print_r($cl->getClusterList());
echo "getClusterHosts:model:\n";
print_r($cl->getClusterHosts('aggr'));

/*
[0] => 138.201.1.1
[1] => 138.201.2.2
[2] => 138.201.3.3
[3] => 138.201.4.4
*/
// ----------------------------------------------------------------------


echo "\n----\nEND\n";
// ----------------------------------------------------------------------
