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
$cl1 = new ClickHouseDB\Cluster($config);
$cl2= new ClickHouseDB\Cluster($config);
$cl2->setScanTimeOut(0.2); // 200 ms


for ($z=0;$z<100;$z++)
{
    $cl1->rescan();
    $cl2->rescan();
    print_r(    $cl1->getHostsBad()        );
    print_r(    $cl2->getHostsBad()        );

}




echo "END\n";
//print_r(    $db->getAllHostsIps()        );

//print_r(         );

//print_r($db->getListHostInCluser('ads')); // like $db->getClustersTable()

// ----------------------------------------------------------------------
