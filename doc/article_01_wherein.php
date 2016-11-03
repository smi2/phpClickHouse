<?php
// Подключаем драйвер
include_once __DIR__ . '/../include.php';


// Конфигрурация
$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];
$client=new \ClickHouseDB\Client($config);
// проверим соединение с базой
$client->ping();
$client->write('CREATE DATABASE IF NOT EXISTS articles');
$client->write('DROP TABLE IF EXISTS articles.events');
$client->write("
CREATE TABLE articles.events 
(event_date  Date DEFAULT toDate(event_time),
event_time  DateTime,
event_type  Enum8('VIEWS' = 1, 'CLICKS' = 2),
site_id   Int32,
article_id   Int32,
ip          String,
city    String,
user_uuid   String,
referer    String,
utm    String DEFAULT extractURLParameter(referer,'utm_campaign')
) engine=MergeTree(event_date, (site_id, event_date,article_id), 8192)

");


// Выбираем default базу
$client->database('articles');

// Получим список таблиц
print_r($client->showTables());


// Для упрощение выставляем принудительно таймзону
date_default_timezone_set('Europe/Moscow');

//Простая вставка данных  `$db->insert(имя_таблицы, [данные] , [колонки]);`

$client->insert('events',
    [
        [time(), 'CLICKS', 1, 1234, '192.168.1.11', 'Moscow','user_11',''],
        [time(), 'CLICKS', 1, 1235, '192.168.1.11', 'Moscow','user_11','http://yandex.ru?utm_campaign=abc'],
        [time(), 'CLICKS', 1, 1236, '192.168.1.11', 'Moscow','user_11','http://smi2.ru?utm_campaign=abc'],
        [time(), 'CLICKS', 1, 1237, '192.168.1.11', 'Moscow','user_11',''],
        [time(), 'CLICKS', 1, 1237, '192.168.1.13', 'Moscow','user_13',''],
        [time(), 'CLICKS', 1, 1237, '192.168.1.14', 'Moscow','user_14',''],
        [time(), 'VIEWS' , 1, 1237, '192.168.1.11', 'Moscow','user_11',''],
        [time(), 'VIEWS' , 1, 1237, '192.168.1.12', 'Moscow','user_12',''],

        [time(), 'VIEWS' , 1, 27  , '192.168.1.1', 'Rwanda','user_55',  'http://smi2.ru?utm_campaign=abc'],
        [time(), 'VIEWS' , 1, 27  , '192.168.1.1', 'Banaadir','user_54','http://smi2.ru?utm_campaign=abc'],
        [time(), 'VIEWS' , 1, 27  , '192.168.1.1', 'Tobruk','user_32',  'http://smi2.ru?utm_campaign=CM1'],
        [time(), 'VIEWS' , 1, 28  , '192.168.1.1', 'Gisborne','user_12','http://smi2.ru?utm_campaign=CM1'],
        [time(), 'VIEWS' , 1, 26  , '192.168.1.1', 'Moscow','user_43',  'http://smi2.ru?utm_campaign=CM3'],
    ],
    ['event_time', 'event_type', 'site_id', 'article_id', 'ip', 'city','user_uuid','referer']
);

print_r($client->select("SELECT count() as count_rows FROM articles.events")->fetchOne());


// Для  WHERE IN - создаем файл CSV
$hand=fopen("/tmp/articles_list.csv",'w');
foreach ([1237,27,1234] as $article_id) {
    fputcsv($hand,[$article_id]);
}
fclose($hand);

//
$whereIn = new \ClickHouseDB\WhereInFile();
$whereIn->attachFile('/tmp/articles_list.csv', 'namex', ['article_id' => 'Int32'], \ClickHouseDB\WhereInFile::FORMAT_CSV);

//
$sql = "
    SELECT 
      article_id, 
      countIf(event_type='CLICKS') as count_clicks, 
      countIf(event_type='VIEWS') as count_views 
    
    FROM articles.events
    WHERE 
          article_id IN (SELECT article_id FROM namex)
    GROUP BY article_id
    ORDER BY count_views DESC
";

$bindings=[];
$statement=$client->select($sql, $bindings, $whereIn);

print_r($statement->rows());
