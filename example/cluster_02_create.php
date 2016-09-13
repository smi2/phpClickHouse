<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/lib_example.php';
// load production config
$config = include_once __DIR__ . '/../../_clickhouse_config_product.php';

$cl = new ClickHouseDB\Cluster($config);
//
//
//
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
//
//if (!$cl->sendMigration($cluster,$sql_up,$sql_down))
//{
//    echo "createCluster:false , ".$cl->getError()."\n";
//    exit;
//}
//echo "createCluster:OK!\n";

// ----------------------------------------------------------------------
$cl = new ClickHouseDB\Cluster($config);

$cl->setScanTimeOut(2.5); // 2500 ms
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}

echo "Ips:\n";
print_r($cl->getNodes());
echo "getClusterList:\n";
print_r($cl->getClusterList());


//
foreach (['pulse','repikator','sharovara','repikator3x','sharovara3x'] as $name)
{
//    print_r($cl->getClusterNodes($name));

    echo "> $name , count shard   = ".$cl->getClusterCountShard($name)." ; count replica = ".$cl->getClusterCountReplica($name)."\n";
}


// ----------------------------------------------------------------------


echo "\n----\nEND\n";
// ----------------------------------------------------------------------
