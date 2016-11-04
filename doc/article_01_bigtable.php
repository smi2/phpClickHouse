<?php
$fileName='/tmp/__articles.big.events_version1.csv';

$count_rows=500000;


// Подключаем драйвер
include_once __DIR__ . '/../include.php';
// Для упрощения выставляем принудительно таймзону
date_default_timezone_set('Europe/Moscow');

//  класс userevent
include_once 'article_01_userevent.php';

// Конфигурация
$config = [
    'host'     => '10.211.5.3',
    'port'     => '8123',
    'username' => 'default',
    'password' => ''
];


$client = new \ClickHouseDB\Client($config);


$client->write('DROP TABLE IF EXISTS articles.events');




if (!$client->isExists('articles','events'))
{

    $client->write('DROP TABLE IF EXISTS articles.events');

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

    @unlink($fileName);
    echo "Write data to : ".$fileName."\n\n";

    for ($z=0;$z<$count_rows;$z++)
    {

        $row = [
            'event_date' => $userEvent->getDate(),
            'event_time' => $userEvent->getTime(),
            'event_type' => $userEvent->getType(),
            'site_id'    => $userEvent->getSiteId(),
            'article_id' => $userEvent->getArticleId(),
            'ip'         => $userEvent->getIp(),
            'city'       => $userEvent->getCity(),
            'user_uuid'  => $userEvent->getUserUuid(),
            'referer'    => $userEvent->getReferer(),
            'utm'    => $userEvent->getUtm(),
        ];
        file_put_contents($fileName,\ClickHouseDB\FormatLine::TSV($row)."\n",FILE_APPEND);
        if ($z%100==0) echo "$z\r";
    }





// Включаем сжатие
    $client->setTimeout(300);
    $client->database('articles');
    $client->enableHttpCompression(true);
    echo "\n> insertBatchFiles....\n";
    $result_insert = $client->insertBatchTSVFiles('events', [$fileName], [
        'event_date',
        'event_time',
        'event_type',
        'site_id',
        'article_id',
        'ip',
        'city',
        'user_uuid',
        'referer',
        'utm'
    ]);
    echo "insert done\n";



    echo $fileName . " : " . $result_insert[$fileName]->totalTimeRequest() . "\n";

}

$client->database('articles');


// Допустим нам нужно посчитать сколько уникальных пользователей просмотрело за сутки
print_r(
    $client->select('
        SELECT
            event_date,
            uniqCombined(user_uuid) as count_users
        FROM
            events
        WHERE
            site_id=1
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
