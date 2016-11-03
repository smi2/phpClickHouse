<?php
// Подключаем драйвер
include_once __DIR__ . '/../include.php';


// Конфигрурация
$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];


$client=new \ClickHouseDB\Client($config);


// проверим соединение с базой
$client->ping();



//
$client->write('CREATE DATABASE IF NOT EXISTS articles');

$client->write('DROP TABLE IF EXISTS articles.events');

$client->write("

CREATE TABLE IF NOT EXISTS articles.events (
    event_date  Date,
    event_time  DateTime,
    event_type  Enum8('VIEWS' = 1, 'CLICKS' = 2),
    site_id   Int32,
    article_id   Int32,
    ip          String,
    city    String,
    user_uuid   String,
    referer    String,
    utm    String
    ) 
    engine=MergeTree(event_date, (site_id, event_date,article_id), 8192)
");

// Выбираем default базу
$client->database('articles');

// Получим список таблиц
print_r($client->showTables());


// Для упрощение выставляем принудительно таймзону
date_default_timezone_set('Europe/Moscow');

//Простая вставка данных  `$db->insert(имя_таблицы, [данные] , [колонки]);`

$stat = $client->insert('events',
    [
        [date('Y-m-d'),time(), 'CLICKS', 1, 1234, '192.168.1.1', 'Moscow','xcvfdsazxc','',''],
        [date('Y-m-d'),time(), 'CLICKS', 1, 1235, '192.168.1.1', 'Moscow','xcvfdsazxc','http://yandex.ru',''],
        [date('Y-m-d'),time(), 'CLICKS', 1, 1236, '192.168.1.1', 'Moscow','xcvfdsazxc','',''],
        [date('Y-m-d'),time(), 'CLICKS', 1, 1237, '192.168.1.1', 'Moscow','xcvfdsazxc','',''],
    ],
    ['event_date', 'event_time', 'event_type', 'site_id', 'article_id', 'ip', 'city','user_uuid','referer','utm']
);

// Достанем результат вставки данных
print_r(
        $client->select('SELECT * FROM events')->rows()
);
