---
layout: default
title: Cluster
---

[< Back to Home](./)

# Cluster

## Setup

```php
$config = [
    'host'     => 'cluster.clickhouse.dns.com', // any node name in cluster
    'port'     => '8123',
    'username' => 'default',                     // all nodes share login/password
    'password' => '',
];

// Client connects to first node by DNS, reads IP list, then connects to ALL nodes for health check
$cl = new ClickHouseDB\Cluster($config);
$cl->setScanTimeOut(2.5); // 2500 ms max per node
```

## Health check

```php
if (!$cl->isReplicasIsOk()) {
    throw new Exception('Replica state is bad, error=' . $cl->getError());
}
```

## Node discovery

```php
// All nodes and clusters
print_r($cl->getNodes());
print_r($cl->getClusterList());

// Nodes by cluster name
$name = 'some_cluster_name';
print_r($cl->getClusterNodes($name));

// Counts
echo "Count Shard   = " . $cl->getClusterCountShard($name) . "\n";
echo "Count Replica = " . $cl->getClusterCountReplica($name) . "\n";
```

## Working with nodes

```php
// Get nodes by table & print size per node
$nodes = $cl->getNodesByTable('shara.adpreview_body_views_sharded');
foreach ($nodes as $node) {
    echo "$node >\n";
    print_r($cl->client($node)->tableSize('adpreview_body_views_sharded'));
    print_r($cl->client($node)->showCreateTable('shara.adpreview_body_views'));
}
```

## Select node by pattern

```php
// Select by IP pattern, delimiter ";"
// First tries .298, then .964, falls back to first node
$cli = $cl->clientLike($name, '.298;.964');
$cli->ping();
```

## Cluster operations

```php
// Truncate table across cluster
$result = $cl->truncateTable('dbName.TableName_sharded');

// Random active node
$cl->activeClient()->setTimeout(500);
$cl->activeClient()->write("DROP TABLE IF EXISTS default.asdasdasd ON CLUSTER cluster2");

// Find leader node
$cl->getMasterNodeForTable('dbName.TableName_sharded');
```

## Errors

```php
var_dump($cl->getError());
```
