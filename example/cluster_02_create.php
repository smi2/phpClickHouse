<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/lib_example.php';
// load production config
$config = include_once __DIR__ . '/../../_clickhouse_config_product.php';


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


// ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------



$mclq=new ClickHouseDB\Cluster\Migration($cluster_name);

$mclq->addSqlUpdate('DROP DATABASE IF EXISTS shara');
$mclq->addSqlUpdate('CREATE DATABASE IF NOT EXISTS shara');
$mclq->addSqlUpdate('DROP TABLE IF EXISTS shara.adpreview_body_views_sharded');
$mclq->addSqlUpdate('DROP TABLE IF EXISTS shara.adpreview_body_views');
$mclq->addSqlUpdate('DROP TABLE IF EXISTS target.adpreview_body_views_sharded');
$mclq->addSqlUpdate('DROP TABLE IF EXISTS target.adpreview_body_views');
$mclq->addSqlUpdate(
"CREATE TABLE IF NOT EXISTS shara.adpreview_body_views_sharded (
    event_date Date DEFAULT toDate(event_time),
    event_time DateTime DEFAULT now(),
    body_id Int32,
    site_id Int32,
    block_id Int32,
    views Int32
) ENGINE = ReplicatedSummingMergeTree('/clickhouse/tables/{sharovara_replica}/shara/adpreview_body_views_sharded', '{replica}', event_date, (event_date, event_time, body_id, site_id, block_id), 8192)
");
$mclq->addSqlUpdate(
"CREATE TABLE IF NOT EXISTS 
shara.adpreview_body_views AS shara.adpreview_body_views_sharded 
ENGINE = Distributed(sharovara, shara, adpreview_body_views_sharded , rand())
");

// откат
$mclq->addSqlDowngrade('DROP TABLE IF EXISTS shara.adpreview_body_views');
$mclq->addSqlDowngrade('DROP TABLE IF EXISTS shara.adpreview_body_views_sharded');
$mclq->addSqlDowngrade('DROP DATABASE IF EXISTS shara');


if (!$cl->sendMigration($mclq))
{
    throw new Exception('sendMigration is bad , error='.$cl->getError());
}


echo "\n----\nEND\n";
// ----------------------------------------------------------------------
