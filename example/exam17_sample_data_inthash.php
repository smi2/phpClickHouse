<?php

include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);
$_flag_create_table=false;


$db->write("DROP TABLE IF EXISTS summing_url_views_intHash32_site_id");



$size=$db->tableSize('summing_url_views_intHash32_site_id');
echo "Site table summing_url_views_intHash32_site_id : ".(isset($size['size'])?$size['size']:'false')."\n";



if (!isset($size['size'])) $_flag_create_table=true;


if ($_flag_create_table) {


    $db->write("DROP TABLE IF EXISTS summing_url_views_intHash32_site_id");
    $re=$db->write('
                CREATE TABLE IF NOT EXISTS summing_url_views_intHash32_site_id (
                    event_date Date DEFAULT toDate(event_time),
                    event_time DateTime,
                    url_hash String,
                    site_id Int32,
                    views Int32,
                    v_00 Int32,
                    v_55 Int32
                ) 
                ENGINE = SummingMergeTree(event_date, intHash32(event_time,site_id),(site_id, url_hash, event_time, event_date,intHash32(event_time,site_id)), 8192)
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
        \ClickHouseDB\Example\Helper::makeSomeDataFileBig($file_name, 4 * $c,$shift_days);
    }

    echo "----------------------------------------------------------------------------------------------------\n";
    echo "insert ALL file async + GZIP:\n";

    $db->enableHttpCompression(true);
    $time_start = microtime(true);

    $result_insert = $db->insertBatchFiles('summing_url_views_intHash32_site_id', $file_data_names, [
        'event_time', 'url_hash', 'site_id', 'views', 'v_00', 'v_55'
    ]);

    echo "use time:" . round(microtime(true) - $time_start, 2) . "\n";

    foreach ($result_insert as $fileName => $state) {
        echo "$fileName => " . json_encode($state->info_upload()) . "\n";
    }





}
echo "------------------------------- COMPARE event_date ---------------------------------------------------------------------\n";

$rows=($db->select('select event_date,sum(views) as v from summing_url_views_intHash32_site_id GROUP BY event_date ORDER BY event_date')->rowsAsTree('event_date'));

$samp=($db->select('select event_date,sum(views) as v from summing_url_views_intHash32_site_id SAMPLE 0.5 GROUP BY event_date ORDER BY event_date ')->rowsAsTree('event_date'));


foreach ($rows as $event_date=>$data)
{
    echo $event_date."\t".$data['v']."\t".(@$samp[$event_date]['v']*(1/0.5))."\n";
}


$rows=($db->select('select site_id,sum(views) as v from summing_url_views_intHash32_site_id GROUP BY site_id ORDER BY site_id')->rowsAsTree('site_id'));

$samp=($db->select('select site_id,(sum(views)) as v from summing_url_views_intHash32_site_id SAMPLE 0.5 GROUP BY site_id ORDER BY site_id ')->rowsAsTree('site_id'));


foreach ($rows as $event_date=>$data)
{
    echo $event_date."\t".$data['v']."\t".intval(@$samp[$event_date]['v'])."\n";
}
/*

Когда мы семплируем данные по ключу intHash32(site_id), и достаем данные  GROUP BY site_id
Сумма показов по ключу site_id даст точное кол-во показов , но в выборке будет отобранно только тот процент который указан

select site_id,(sum(views)) as v from summing_url_views_intHash32_site_id SAMPLE 0.1 GROUP BY site_id ORDER BY site_id
VS
select site_id,sum(views) as v from summing_url_views_intHash32_site_id GROUP BY site_id ORDER BY site_id



48	16560	0
47	16560	0
46	16560	16560
45	16560	0
44	16560	0
43	16560	0
42	16560	0
41	16560	0
40	16560	0
39	16560	0
38	16560	16560
37	16560	0
36	16560	16560
35	16560	0
34	16560	0
33	16560	16560
32	16560	0
31	16560	0
30	16560	0
29	16560	0
28	16560	0
27	16560	0
26	16560	0
25	16560	0
24	16560	0
23	16560	0
22	16560	0
21	16560	0
20	16560	16560
19	16560	0
18	16560	0
17	16560	0
16	16560	0
15	16560	0
14	16560	0
13	16560	0
12	16560	0




Если увеличить SAMPLE 0.5 => 50% прочитвется по ключу site_id

48	16560	0
47	16560	0
46	16560	16560
45	16560	16560
44	16560	16560
43	16560	0
42	16560	16560
41	16560	16560
40	16560	16560
39	16560	16560
38	16560	16560
37	16560	16560
36	16560	16560
35	16560	16560
34	16560	0
33	16560	16560
32	16560	16560
31	16560	16560
30	16560	16560
29	16560	0
28	16560	16560
27	16560	16560
26	16560	0
25	16560	0
24	16560	0
23	16560	0
22	16560	0
21	16560	16560
20	16560	16560
19	16560	16560
18	16560	16560
17	16560	16560
16	16560	0
15	16560	16560
14	16560	16560
13	16560	16560
12	16560	16560

 */