<?php
include_once __DIR__.'/../include.php';

$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];

$db=new ClickHouseDB\Client($config);


$db->write("DROP TABLE IF EXISTS summing_url_views");
$db->write(
    '
CREATE TABLE IF NOT EXISTS summing_url_views (
event_date Date DEFAULT toDate(event_time),
event_time DateTime,
url_hash String,
site_id Int32,
views Int32,
v_00 Int32,
v_55 Int32
) ENGINE = SummingMergeTree(event_date, (site_id, url_hash, event_time, event_date), 8192)
'
);
echo "Table EXISTSs:".json_encode($db->showTables())."\n";
// --------------------------------  CREATE csv file ----------------------------------------------------------------

function makeSomeDataFile($file_name,$size=10)
{


    @unlink($file_name);


    $handle = fopen($file_name,'w');
    $z=0;$rows=0;
    $j=[];
    for($ules=0;$ules<$size;$ules++)
        for($dates=0;$dates<5;$dates++)
        {
            for ($site_id=12;$site_id<49;$site_id++)
            {
                for ($hours=0;$hours<24;$hours++)
                {
                    $z++;
                    $dt=strtotime('-'.$dates.' day');
                    $dt=strtotime('-'.$hours.' hour',$dt);
                    $j=[];
                    $j['event_time']=date('Y-m-d H:00:00',$dt);
                    $j['url_hash']='XXXX'.$site_id.'_'.$ules;
                    $j['site_id']=$site_id;
                    $j['views']=1;

                    foreach (['00',55] as $key)
                    {
                        $z++;
                        $j['v_'.$key]=($z%2?1:0);
                    }
                    fputcsv($handle,$j);
                    $rows++;
                }
            }
        }

    fclose($handle);

    echo "Created file  [$file_name]: $rows rows...\n";
}



// ----------------------------------------------------------------------------------------------------




$file_data_names=[
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
    '/tmp/clickHouseDB_test.3.data',
    '/tmp/clickHouseDB_test.4.data',
    '/tmp/clickHouseDB_test.5.data',
];

foreach ($file_data_names as $file_name)
{
    makeSomeDataFile($file_name,5);
}
// ----------------------------------------------------------------------------------------------------
echo "insert ONE file:\n";

$time_start=microtime(true);
$stat=$db->insertBatchFiles('summing_url_views',['/tmp/clickHouseDB_test.1.data'],['event_time','url_hash','site_id','views','v_00','v_55']);
echo "use time:".round(microtime(true)-$time_start,2)."\n";

print_r($db->select('select sum(views) from summing_url_views')->rows());

echo "insert ALL file async:\n";

$time_start=microtime(true);
$stat=$db->insertBatchFiles('summing_url_views',$file_data_names,['event_time','url_hash','site_id','views','v_00','v_55']);
echo "use time:".round(microtime(true)-$time_start,2)."\n";







print_r($db->select('select sum(views) from summing_url_views')->rows());

// ------------------------------------------------------------------------------------------------
/*
Table EXISTSs:[{"name":"summing_url_views"}]
Created file  [/tmp/clickHouseDB_test.1.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.2.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.3.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.4.data]: 22200 rows...
Created file  [/tmp/clickHouseDB_test.5.data]: 22200 rows...
insert ONE file:
use time:0.7
Array
(
    [0] => Array
        (
            [sum(views)] => 22200
        )

)
insert ALL file async:
use time:0.74
Array
(
    [0] => Array
        (
            [sum(views)] => 133200
        )

)

 */