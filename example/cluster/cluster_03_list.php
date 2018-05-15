<?php
include_once __DIR__ . '/../../include.php';
include_once __DIR__ . '/../Helper.php';
\ClickHouseDB\Example\Helper::init();

$config = include_once __DIR__ . '/00_config_connect.php';


$cl = new ClickHouseDB\Cluster($config);


if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}
$cluster_name='sharovara';

echo "> $cluster_name , count shard   = ".$cl->getClusterCountShard($cluster_name)." ; count replica = ".$cl->getClusterCountReplica($cluster_name)."\n";


// ------------------------------------------------------------------------------------------------------------------------------------------------------------------------
$nodes=$cl->getNodesByTable('shara.adpreview_body_views_sharded');

foreach ($nodes as $node)
{
    echo "$node > \n";
    print_r($cl->client($node)->tableSize('adpreview_body_views_sharded'));
    print_r($cl->client($node)->showCreateTable('shara.adpreview_body_views'));
}
// ------------------------------------------------------------------------------------------------------------------------------------------------------------------------
