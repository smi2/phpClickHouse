<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);
$_flag_create_table=false;

$size=$db->tableSize('summing_url_views_cityHash64_site_id');
echo "Site table summing_url_views_cityHash64_site_id : ".(isset($size['size'])?$size['size']:'false')."\n";


if (!isset($size['size'])) $_flag_create_table=true;


if ($_flag_create_table) {


    $db->write("DROP TABLE IF EXISTS summing_url_views_cityHash64_site_id");
    $re=$db->write('
                CREATE TABLE IF NOT EXISTS summing_url_views_cityHash64_site_id (
                    event_date Date DEFAULT toDate(event_time),
                    event_time DateTime,
                    url_hash String,
                    site_id Int32,
                    views Int32,
                    v_00 Int32,
                    v_55 Int32
                ) 
                ENGINE = SummingMergeTree(event_date, cityHash64(site_id,event_time),(site_id, url_hash, event_time, event_date,cityHash64(site_id,event_time)), 8192)
            ');
    echo "Table EXISTS:" . print_r($db->showTables()) . "\n";
    // ------------------------------------------------------------------------------------------------------

    echo "----------------------------------- CREATE big csv file -----------------------------------------------------------------\n";


    $file_data_names = [
        '/tmp/clickHouseDB_test.big.1.data',
        '/tmp/clickHouseDB_test.big.2.data',
        '/tmp/clickHouseDB_test.big.3.data',
    ];

    $c = 0;
    foreach ($file_data_names as $file_name) {
        $c++;
        $shift_days=( -1* $c*3);
        \ClickHouseDB\Example\Helper::makeSomeDataFileBig($file_name, 23 * $c,$shift_days);
    }

    echo "----------------------------------------------------------------------------------------------------\n";
    echo "insert ALL file async + GZIP:\n";

    $db->enableHttpCompression(true);
    $time_start = microtime(true);

    $result_insert = $db->insertBatchFiles('summing_url_views_cityHash64_site_id', $file_data_names, [
        'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
    ]);

    echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";

    foreach ($result_insert as $fileName => $state) {
        echo "$fileName => " . json_encode($state->info_upload()) . "\n";
    }





}
echo "------------------------------- COMPARE event_date ---------------------------------------------------------------------\n";

$rows=($db->select('select event_date,sum(views) as v from summing_url_views_cityHash64_site_id GROUP BY event_date ORDER BY event_date')->rowsAsTree('event_date'));

$samp=($db->select('select event_date,(sum(views)*10) as v from summing_url_views_cityHash64_site_id SAMPLE 0.1 GROUP BY event_date ORDER BY event_date ')->rowsAsTree('event_date'));


foreach ($rows as $event_date=>$data)
{
    echo $event_date."\t".$data['v']."\t".@$samp[$event_date]['v']."\n";
}


$rows=($db->select('select site_id,sum(views) as v from summing_url_views_cityHash64_site_id GROUP BY site_id ORDER BY site_id')->rowsAsTree('site_id'));

$samp=($db->select('select site_id,(sum(views)) as v from summing_url_views_cityHash64_site_id SAMPLE 0.5 GROUP BY site_id ORDER BY site_id ')->rowsAsTree('site_id'));


foreach ($rows as $event_date=>$data)
{
    echo $event_date."\t".$data['v']."\t".intval(@$samp[$event_date]['v'])."\n";
}



for($f=1;$f<=9;$f++)
{
    $SAMPLE=$f/10;

    $CQL='select site_id,(sum(views)) as v from summing_url_views_cityHash64_site_id SAMPLE '.$SAMPLE.' WHERE site_id=34 GROUP BY site_id ORDER BY site_id ';

    echo $CQL."\n";
    $rows=($db->select('select site_id,sum(views) as v from summing_url_views_cityHash64_site_id WHERE site_id=34 GROUP BY site_id ORDER BY site_id')->rowsAsTree('site_id'));
    $samp=($db->select($CQL)->rowsAsTree('site_id'));
    foreach ($rows as $id=>$data)
    {
            $s=$samp[$id]['v'];
            $v=$data['v'];


            $percent=round( (100*$s) /$v   ,2);

            $kof=(100/$percent);
            $norma_views=$s*(100/$percent);



            echo "Сумма показов без SAMPLE  =  " .$v."\n";
            echo "Сумма показов c   SAMPLE  =  " .$s."\n";
            echo "Процент                   =  " .$percent."\n";
            echo "На что домжнож.семлир.данн=  " .$kof."\n";
            echo "Сумма показов расчитанное =  " .$norma_views."\n";

/// >> 1/(0.8) = для SAMPLE 0.8
/// >> 1/(0.5) = для SAMPLE 0.5

    }

    echo "\n\n";
}



/*





 */