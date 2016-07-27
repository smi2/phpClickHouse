<?php

include_once __DIR__.'/../include.php';
include_once __DIR__.'/lib_example.php';
$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];
include_once __DIR__.'/../../_clickhouse_config_product.php';



$start_time=microtime(true);

$db=new ClickHouseDB\Client($config);
$db->database('aggr');
print_r(
    $db->select('select event_date,site_id,group,sum(views) as views from aggr.summing_url_views
where event_date=today() and site_id=14776
group by event_date,site_id,group
order by views DESC
LIMIT 3')->rows()

);




$sql='select site_id,group,sum(views) as views from aggr.summing_url_views
where event_date=today() and 
( 
site_id IN (SELECT site_id FROM namex)
OR 
site_id IN (SELECT site_id FROM site_keys)
)
group by site_id,group
order by views DESC
LIMIT 5
';



// some file names to data
$file_name_data1="/tmp/temp_csv.txt";

$file_name_data2="/tmp/site_keys.data";

// create CSV file
makeListSitesKeysDataFile($file_name_data1,1000,2000); // see lib_example.php
makeListSitesKeysDataFile($file_name_data2,5000,6000); // see lib_example.php




// create WhereInFile
$whereIn=new \ClickHouseDB\WhereInFile();


// attachFile( {full_file_path} , {data_table_name} , [ { structure } ]

$whereIn->attachFile($file_name_data1,'namex',['site_id'=>'Int32','site_hash'=>'String'],\ClickHouseDB\WhereInFile::FORMAT_CSV);
$whereIn->attachFile($file_name_data2,'site_keys',['site_id'=>'Int32','site_hash'=>'String'],\ClickHouseDB\WhereInFile::FORMAT_CSV);

$result=$db->select($sql,[],$whereIn);
print_r($result->rows());

// ----------------------------------------------- ASYNC ------------------------------------------------------------------------------------------
echo "\n----------------------- ASYNC ------------ \n";

$sql='select site_id,group,sum(views) as views from aggr.summing_url_views
where event_date=today() and site_id IN (SELECT site_id FROM namex)
group by site_id,group
order by views DESC
LIMIT {limit}
';


$bindings['limit']=3;

$statements=[];
$whereIn=new \ClickHouseDB\WhereInFile();
$whereIn->attachFile($file_name_data1,'namex',['site_id'=>'Int32','site_hash'=>'String'],\ClickHouseDB\WhereInFile::FORMAT_CSV);

$statements[0]=$db->selectAsync($sql,$bindings,$whereIn);


// change data file - for statement two
$whereIn=new \ClickHouseDB\WhereInFile();
$whereIn->attachFile($file_name_data2,'namex',['site_id'=>'Int32','site_hash'=>'String'],\ClickHouseDB\WhereInFile::FORMAT_CSV);

$statements[1]=$db->selectAsync($sql,$bindings,$whereIn);

$db->executeAsync();


foreach ( $statements as $statement)
{
    print_r($statement->rows());
}


/*

Не перечисляйте слишком большое количество значений (миллионы) явно.
Если множество большое - лучше загрузить его во временную таблицу (например, смотрите раздел "Внешние данные для обработки запроса"), и затем воспользоваться подзапросом.

Внешние данные для обработки запроса

При использовании HTTP интерфейса, внешние данные передаются в формате multipart/form-data. Каждая таблица передаётся отдельным файлом.
Имя таблицы берётся из имени файла.
В query_string передаются параметры name_format, name_types, name_structure, где name - имя таблицы, которой соответствуют эти параметры. Смысл параметров такой же, как при использовании клиента командной строки.

Пример:

cat /etc/passwd | sed 's/:/\t/g' > passwd.tsv

curl -F 'passwd=@passwd.tsv;' 'http://localhost:8123/?query=SELECT+shell,+count()+AS+c+FROM+passwd+GROUP+BY+shell+ORDER+BY+c+DESC&passwd_structure=login+String,+unused+String,+uid+UInt16,+gid+UInt16,+comment+String,+home+String,+shell+String'
/bin/sh 20
/bin/false      5
/bin/bash       4
/usr/sbin/nologin       1
/bin/sync       1
При распределённой обработке запроса, временные таблицы передаются на все удалённые серверы.

*/