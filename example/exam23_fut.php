<?php
include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';


//


$cl = new ClickHouseDB\Client($config);

print_r($cl->getServerUptime());
print_r($cl->getServerSystemSettings());
print_r($cl->getServerSystemSettings('merge_tree_min_rows_for_concurrent_read'));
// ------------------------