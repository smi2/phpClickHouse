<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/lib_example.php';

$config = [
    'host' => '192.168.1.20',
    'port' => '8123',
    'username' => 'default',
    'password' => ''
];

//
include_once __DIR__ . '/../../_clickhouse_config_product.php';


// in smi2 - DNS Round-Robin
// host =  db1.clickhouse.smi2.ru  A record  => [ db1.clickhouse1.smi2.ru,db1.clickhouse2.smi2.ru,db1.clickhouse3.smi2.ru....]
// findActiveHostAndCheckCluster - ping all IPs in DNS record
// random() select from active
// if develop server one IP or host - no check

///
// see clusert_***.php examples