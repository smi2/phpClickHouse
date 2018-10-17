<?php

include_once __DIR__ . '/../../include.php';
include_once __DIR__ . '/../Helper.php';
\ClickHouseDB\Example\Helper::init();
// load production config
$config = include_once __DIR__ . '/00_config_connect.php';



$db = new ClickHouseDB\Client($config);
$db->settings()->set('replication_alter_partitions_sync',2);
$db->settings()->set('experimental_allow_extended_storage_definition_syntax',1);


for ( $looop=1;$looop<100;$looop++)
{

    $db->write("DROP TABLE IF EXISTS testoperation_log");
    $db->write("
CREATE TABLE IF NOT EXISTS `testoperation_log` (
    `event_date` Date default toDate(time),
    `event` String  DEFAULT '',
    `time` DateTime default  now()
) ENGINE=MergeTree ORDER BY time PARTITION BY event_date

");

    echo "INSERT DATA....\n";
    for ($z=0;$z<1000;$z++)
    {
        $dataInsert=['time'=>strtotime('-'.mt_rand(0,4000).' day'),'event'=>strval($z)];
        try {
            $db->insertAssocBulk('testoperation_log',$dataInsert);
            echo "$z\r";
        }
        catch (Exception $exception)
        {
            die("Error:".$exception->getMessage());
        }

    }
    echo "INSER OK\n DROP PARTITION...\n";

    $partitons=($db->partitions('testoperation_log'));
    foreach ($partitons as $part)
    {
        echo "$looop\t\t".$part['partition']."\t".$part['name']."\t".$part['active']."\r";

        $db->dropPartition('default.testoperation_log',$part['partition']);
    }
    echo "SELECT count() ...".str_repeat(" ",300)."\n";
    print_r($db->select('SELECT count() FROM default.testoperation_log')->rows());
}

echo "\n----\nEND\n";
// ----------------------------------------------------------------------

