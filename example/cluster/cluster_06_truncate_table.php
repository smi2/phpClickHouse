<?php

include_once __DIR__ . '/../../include.php';
include_once __DIR__ . '/../Helper.php';
\ClickHouseDB\Example\Helper::init();
// load production config
$config = include_once __DIR__ . '/00_config_connect.php';


$cl = new ClickHouseDB\Cluster($config);

$cl->setScanTimeOut(2.5); // 2500 ms
$cl->setSoftCheck(true);
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}


$tables=$cl->getTables();

foreach ($tables as $dbtable=>$tmp)
{
    echo ">>> $dbtable :";

    $size=$cl->getSizeTable($dbtable);


    echo "\t".\ClickHouseDB\Example\Helper::humanFileSize($size)."\n";

}


$table_for_truncate='target.events_sharded';

$result=$cl->truncateTable($table_for_truncate);

echo "Result:truncate table\n";
print_r($result);

echo "\n----\nEND\n";
// ----------------------------------------------------------------------

