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
$db = new ClickHouseDB\Cluster($cluster_name,$config);                             // |
print_r(    $db->getHostsIps()        );                             // |
print_r(    $db->getHostsNames()      );                             // |
// ----------------------------------------------------------------------
