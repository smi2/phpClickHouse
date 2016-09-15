<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/lib_example.php';
// load production config
$config = include_once __DIR__ . '/../../_clickhouse_config_product.php';

$cl = new ClickHouseDB\Cluster($config);
//$sql_up=['CREATE DATABASE IF NOT EXISTS cluster_tests',
//
//'
//CREATE TABLE IF NOT EXISTS cluster_tests.sum_views_sharded (
//    event_date Date DEFAULT toDate(event_time),
//    event_time DateTime DEFAULT now(),
//    site_id Int32,
//    views Int32,
//    bad_views Int32
//) ENGINE = ReplicatedSummingMergeTree(\'/clickhouse/tables/{aggr_replica}/aggr/sum_views_sharded\', \'{replica}\', event_date, (event_date,event_time,site_id), 8192)
//',
//'
//CREATE TABLE IF NOT EXISTS cluster_tests.sum_views AS cluster_tests.sum_views_sharded ENGINE = Distributed(aggr, cluster_tests, sum_views_sharded, rand())
//'
//];
//$sql_down=[
//        'DROP DATABASE IF EXISTS cluster_tests',
//        'DROP TABLE IF EXISTS cluster_tests.sum_views_sharded',
//        'DROP TABLE IF EXISTS cluster_tests.sum_views',
//];
// ----------------------------------------------------------------------
$cl = new ClickHouseDB\Cluster($config);

$cl->setScanTimeOut(2.5); // 2500 ms
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}
//
$cluster_name='sharovara';
//
echo "> $cluster_name , count shard   = ".$cl->getClusterCountShard($cluster_name)." ; count replica = ".$cl->getClusterCountReplica($cluster_name)."\n";


// ----------------------------------------------------------------------
$sql_up=['CREATE DATABASE IF NOT EXISTS cluster_tests'];
$sql_down=['DROP DATABASE IF EXISTS cluster_tests'];



$mclq=new ClickHouseDB\Cluster\Migration($cluster_name);

$mclq->setUpdate('CREATE DATABASE IF NOT EXISTS cluster_tests');
$mclq->setDowngrade('DROP DATABASE IF EXISTS cluster_tests');



if (!$cl->sendMigration($mclq))
{
    throw new Exception('sendMigration is bad , error='.$cl->getError());
}

echo "\n----\nEND\n";
// ----------------------------------------------------------------------
