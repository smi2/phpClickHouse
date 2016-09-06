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
$db = new ClickHouseDB\Cluster($config);
print_r(    $db->getAllHostsIps()        );
print_r(    $db->getHostsBad()        );

//print_r(         );

print_r($db->getListHostInCluser('ads')); // like $db->getClustersTable()

// ----------------------------------------------------------------------
