<?php

// Подключаем драйвер
include_once __DIR__ . '/../include.php';


// Конфигурация
$config = [
    'host'     => '192.168.1.20',
    'port'     => '8123',
    'username' => 'default',
    'password' => ''
];

$client = new \ClickHouseDB\Client($config);

// Проверяем соединение с базой
$client->ping();

// Создаём таблицу
$client->write('CREATE DATABASE IF NOT EXISTS articles');
$client->write('DROP TABLE IF EXISTS articles.events');
$client->write("
    CREATE TABLE articles.events (
        event_date Date DEFAULT toDate(event_time),
        event_time DateTime,
        event_type Enum8('VIEWS' = 1, 'CLICKS' = 2),
        site_id Int32,
        article_id Int32,
        ip String,
        city String,
        user_uuid String,
        referer String,
        utm String DEFAULT extractURLParameter(referer, 'utm_campaign')
    ) ENGINE = MergeTree(event_date, (site_id, event_date, article_id), 8192)
");


// Выбираем default базу
$client->database('articles');

// Получим список таблиц
print_r($client->showTables());


// Для упрощения выставляем принудительно таймзону
date_default_timezone_set('Europe/Moscow');

// Простая вставка данных `$db->insert(имя_таблицы, [данные], [колонки]);`
$client->insert('events',
    [
        [time(), 'CLICKS', 1, 1234, '192.168.1.11', 'Moscow', 'user_11', ''],
        [time(), 'CLICKS', 1, 1235, '192.168.1.11', 'Moscow', 'user_11', 'http://yandex.ru?utm_campaign=abc'],
        [time(), 'CLICKS', 1, 1236, '192.168.1.11', 'Moscow', 'user_11', 'http://smi2.ru?utm_campaign=abc'],
        [time(), 'CLICKS', 1, 1237, '192.168.1.11', 'Moscow', 'user_11', ''],
        [time(), 'CLICKS', 1, 1237, '192.168.1.13', 'Moscow', 'user_13', ''],
        [time(), 'CLICKS', 1, 1237, '192.168.1.14', 'Moscow', 'user_14', ''],
        [time(), 'VIEWS',  1, 1237, '192.168.1.11', 'Moscow', 'user_11', ''],
        [time(), 'VIEWS',  1, 1237, '192.168.1.12', 'Moscow', 'user_12', ''],

        [time(), 'VIEWS',  1, 1237, '192.168.1.1', 'Rwanda',   'user_55', 'http://smi2.ru?utm_campaign=abc'],
        [time(), 'VIEWS',  1, 1237, '192.168.1.1', 'Banaadir', 'user_54', 'http://smi2.ru?utm_campaign=abc'],
        [time(), 'VIEWS',  1, 1237, '192.168.1.1', 'Tobruk',   'user_32', 'http://smi2.ru?utm_campaign=CM1'],
        [time(), 'VIEWS',  1, 1237, '192.168.1.1', 'Gisborne', 'user_12', 'http://smi2.ru?utm_campaign=CM1'],
        [time(), 'VIEWS',  1, 1237, '192.168.1.1', 'Moscow',   'user_43', 'http://smi2.ru?utm_campaign=CM3'],
    ],
    ['event_time', 'event_type', 'site_id', 'article_id', 'ip', 'city', 'user_uuid', 'referer']
);

// Достанем результат вставки данных
print_r(
    $client->select('SELECT * FROM events')->rows()
);


// Допустим нам нужно посчитать сколько уникальных пользователей просмотрело за сутки
print_r(
    $client->select('
        SELECT 
            event_date, 
            uniqCombined(user_uuid) as count_users 
        FROM 
            events 
        GROUP BY 
            event_date 
        ORDER BY 
            event_date
    ')->rows()
);



// Сколько пользователей, которые просматривали и совершили клики
print_r(
    $client->select("
        SELECT 
            user_uuid,
            count() as clicks
        FROM 
            articles.events
        WHERE
            event_type = 'CLICKS' 
            AND site_id = 1
            AND user_uuid IN  (
                SELECT 
                    user_uuid 
                FROM 
                    articles.events 
                WHERE 
                    event_type = 'VIEWS' 
                GROUP BY 
                    user_uuid
            )
        GROUP BY user_uuid
    ")->rows()
);




// Посчитаем ботов, это очень грубо, но возможно оценить через кол-во запросов с одного IP и кол-во уникальных UUID
print_r(
    $client->select('
        /* показывать в отчёте только IP, по которым было хотя бы 4 уникальных посетителей. */
        SELECT 
            ip,
            uniqCombined(user_uuid) as count_users 
        FROM 
            events 
        WHERE 
            event_date = today() 
            AND site_id=1
        GROUP BY 
            ip
        HAVING 
            count_users >= 4
    ')->rows()
);


// Какие UTM метки давали большое кол-во показов:
print_r(
    $client->select("
        SELECT 
            utm,
            count() as views 
        FROM 
            events 
        WHERE 
            event_date = today() 
            AND event_type = 'VIEWS' 
            AND utm <> '' 
            AND site_id = 1
        GROUP BY 
            utm
        ORDER BY 
            views DESC
    ")->rows()
);
