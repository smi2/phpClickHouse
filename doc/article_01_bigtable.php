<?php
$fileName='/tmp/__articles.big.events_version1.csv';



// Подключаем драйвер
include_once __DIR__ . '/../include.php';
// Для упрощения выставляем принудительно таймзону
date_default_timezone_set('Europe/Moscow');

//  класс userevent
include_once 'article_01_userevent.php';

// Конфигурация
$config = [
    'host'     => '192.168.1.20',
    'port'     => '8123',
    'username' => 'default',
    'password' => ''
];

$client = new \ClickHouseDB\Client($config);
$client->write('DROP TABLE IF EXISTS articles.events');
if (!$client->isExists('articles','events'))
{


    $client->write('CREATE DATABASE IF NOT EXISTS articles');
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
    ) ENGINE = MergeTree(event_date, (site_id,event_type, article_id), 8192)
");

    // ---------------------------- создадим тестовый набор данных ---------------
    $userEvent=new UserEvent();
    $count_rows=10;

    unlink($fileName);
    echo "Write data to : ".$fileName."\n\n";
//    $fp = fopen($fileName, 'w');
    for ($z=0;$z<$count_rows;$z++)
    {

        $row = [
            'event_time' => $userEvent->getTime(),
            'event_type' => $userEvent->getType(),
            'site_id'    => $userEvent->getSiteId(),
            'article_id' => $userEvent->getArticleId(),
            'ip'         => $userEvent->getIp(),
            'city'       => $userEvent->getCity(),
            'user_uuid'  => $userEvent->getUserUuid(),
            'referer'    => $userEvent->getReferer(),
        ];
        file_put_contents($fileName,\ClickHouseDB\FormatLine::CSV($row)."\r\n",FILE_APPEND);
        if ($z%10000==0) echo "$z\r";
    }
//    fclose($fp);




// Включаем сжатие
    $client->setTimeout(300);
    $client->database('articles');
    $client->enableHttpCompression(true);
    echo "\n> insertBatchFiles....\n";
    $insertResult = $client->insertBatchFiles('events', [$fileName], [
        'event_time',
        'event_type',
        'site_id',
        'article_id',
        'ip',
        'city',
        'user_uuid',
        'referer'
    ]);
    echo "insert done\n";
}


