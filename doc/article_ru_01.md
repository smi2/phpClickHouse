## clickhouse и php 


Мы создали в СМИ2, http драйвер, обертку над curl для работы с Clickhouse из PHP и Web интерфейс для удобного создания запросов.


Web интерфейс:https://github.com/smi2/clickhouse-frontend


Реализованы специфичный возможности CH, и фичи php драйвера:
* Отсутствие зависимостей, только curl и json,   
* Работа с кластером CH, получение список  node,
* Проверка работы каждой node при подключении к кластеру,
* Выполнение запроса на каждой node в кластере, схоже с миграциями,
* Поддержка сжатия на лету, при записи данных в CH,
* Асинхронное выполнение запросов на чтение,
* Асинхронное выполнение запросов на вставку данных,
* Запрос на чтение, используея локальный InsertRow файл, select * from X where id in (_local_csv_file_),  
* Работа с партициями таблиц,
* Вставка массива в колонку,
* Запись результата запроса напрямую в файл,
* Получение размера таблицы,базы и списока процессов на каждой node 
* Получение статистики выполение запроса select
* Шаблонизация запросов 



Драйвер протестированн на php 5.6 и 7 , hhvm 3.9



# Приступим 


Допустим у нас есть несколько сайтов

| site_id | Домен  |
| :----: |:-------------:|
| 1 | smi2.ru |
| 2 | smi2.ua |
| 3 | smi2.kz |



на каждом из них производятся события, клики и показы статей, article_id

Каждая aricle_id
- имеет источник ria.ru,Известия,RenTv,EA daily,Газета.ру,Tass...
- заголовок

Список сайтов и список статей хранится в базе MySQL, которое являются для CH словарями.



Получается у нас несколько событий которые мы регистрируем :
Это показ - VIEWS и клик CLICKS, также по каждому событию мы получаем несколько атрибутов:
ip адрес пользовтеля,
Имя провайдера - isp
Имя браузера - browser_name
Уникальный ID пользователя - user_uuid
Допустим мы хотим, также используя  внутренный алгоритм, фиксировать: Плохой или хороший клик, это значение good или bad в колонке click_status

Создадим дополнительное поле uuid_sip, которое содержит числовой hash от значения поля user_uuid,
т/е при вставке CH автоматически вычислит значение поля uuid_sip используя ф-цию sipHash64 от значения поля user_uuid
Также чтобы не писать каждый раз поле event_date, его значение можно вычислять из поля event_time

Для записи в мы создадим базу данных в CH , `articles` и в нутри нее  таблицу : `events`, со следующей стуктурой :



```
event_date	Date	DEFAULT	toDate(event_time)
event_time	DateTime
event_type	Enum8('VIEWS' = 1, 'CLICKS' = 2)
aricle_id	Int32
ip			String
isp			String
browser_name	String
user_uuid	String
click_status	String
uuid_sip		UInt64	DEFAULT	sipHash64(user_uuid)
```

Подключаемся к CH, через нашу прослойку:
```php
$db = new ClickHouseDB\Client($config);
$db->write('
    CREATE TABLE IF NOT EXISTS summing_url_views (
        event_date Date DEFAULT toDate(event_time),
        event_time DateTime,
        site_id Int32,
        site_key String,
        views Int32,
        v_00 Int32,
        v_55 Int32
    )
    ENGINE = SummingMergeTree(event_date, (site_id, site_key, event_time, event_date), 8192)
');
```




------
Каждое событие, мы будем писать в InsertRow файл средствами PHP

Note1 : event_time - это unixtime, в php ф-ция time(), нужно помнить в каком часовом поясе мы пишем это значение.







----
----


----



----



## Установка драйвера




```
composer require smi2/phpclickhouse
```

Или используя git 

```
git clone https://github.com/smi2/phpClickHouse.git 
```

При использовании git, подключаем драйвер через `include_once 'phpClickHouse/include.php';`

Есть спицифичное требование, для ускорения работы под HHVM, не использовать `autoload`, поэтому в `include.php` перечисленны все необходимые файлы драйвера. 
То есть нет PSR-4. Дополнительно драйвер использует спиецифичные возможности CH, поэтому реализация через guzzle/PSR-7 оказалась трудозатратной и даже не рассматривалась. 


Если вы ярый сторонник PSR-[4|7], просьба не читать далее, используйте готовые драйверы CH на Guzzle.


Или git submodule

```
git submodule add https://github.com/smi2/phpClickHouse.git
git submodule init
git submodule update --init --recursive
```

## Приступаем к работе 


Соединение с базой, с одной нодой: 
```php
$config = [
    'host' => '192.168.1.1', 
    'port' => '8123', 
    'username' => 'default', 
    'password' => ''
];
// дополнительные настройки, передаваемые с каждым запросом
$settings=['max_execution_time' => 100];

$db = new ClickHouseDB\Client($config,$settings);
$db->database('default');
```

Если нужно соединиться и выполнить запросы на весь кластер - используем другой класс, 
который позволяет выбирать node или получать список node, c которыми работать исходя из имени кластера:

```php
$cluster_name='sharavara';

$cl = new ClickHouseDB\Cluster($config,$settings);
$nodes=$cl->getClusterNodes($cluster_name)

$db=$cl->client($nodes[0]);
$db->database('default');
```



```php
// Получаем спискок таблиц в выбранной базе
print_r($db->showTables());


// Размер таблиц
$db->tableSize("table_name");

// Список баз
$db->showDatabases();

// 
$db->showProcesslist();

```

## Запросы 

Запросы разделены:
* запись 
* вставку данных 
* чтение
 
При этом чтение и вставка может быть асинхронными, т/е выполняться паралелньо используя curl_multi запросы. 

Запросы на запись  и вставку данных - не содержат ответа
Запросы на чтение - содержат ответ, исключение прямая запись ответа в файл. 


### Запросы на запись



```php
$db->write('
    CREATE TABLE IF NOT EXISTS summing_url_views (
        event_date Date DEFAULT toDate(event_time),
        event_time DateTime,
        site_id Int32,
        site_key String,
        views Int32,
        v_00 Int32,
        v_55 Int32
    ) 
    ENGINE = SummingMergeTree(event_date, (site_id, site_key, event_time, event_date), 8192)
';


$db->write('ALTER TABLE summing_url_views DROP PARTITION :partion_id',['partion_id'=>12345]);


$db->write('DROP TABLE IF EXISTS summing_url_views');
```


 
### Запросы на вставку данных


Простая вставка данных  `$db->insert(имя_таблицы, [данные] , [колонки]);`

```php
$stat = $db->insert('summing_url_views', 
    [
        [time(), 'HASH1', 2345, 22, 20, 2],
        [time(), 'HASH2', 2345, 12, 9,  3],
        [time(), 'HASH3', 5345, 33, 33, 0],
        [time(), 'HASH3', 5345, 55, 0, 55],
    ],
    ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
);
```

Асинхронная вставка InsertRow файлов `$db->insertBatchFiles(имя_таблицы, [список_файлов] , [колонки])`



```php
$file_data_names = [
    '/tmp/clickHouseDB_test.1.InsertRow',
    '/tmp/clickHouseDB_test.2.InsertRow',
    '/tmp/clickHouseDB_test.3.InsertRow',
];

// insert all files
$result_insert = $db->insertBatchFiles(
    'summing_url_views', // имя табилци
    $file_data_names,
    ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
);

foreach ($result_insert as $fileName => $state) 
{
   echo $fileName . ' => ' . json_encode($state->info_upload()) . PHP_EOL;
}
```


При больших обьемах вставляемых файлов, можно включить режим потокового сжатия, и ускорить время выполнения запроса: 
Используется режим сжатия в realtime, без создания временных файлов, что позволяет экономить на сетевых ресурсах сервера, сдедовательно скорость передачи возрастает и суммарное время затрченное на один файл уменьшается в несколько раз.   

```php
$db->settings()->max_execution_time(200);
$db->enableHttpCompression(true);

$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [...]);


foreach ($result_insert as $fileName => $state) {
    echo $fileName . ' => ' . json_encode($state->info_upload()) . PHP_EOL;
}
```  



### Запросы на чтение


```php

$state1 = $db->selectAsync('SELECT 1 as ping');
$state2 = $db->selectAsync('SELECT 2 as ping');
// run
$db->executeAsync();
```

#### Запросы на чтение используя локальный файл

Допустим у нас есть созданыый InsertRow файл, содержащий две колонки site_id,site_hash 
 
Мы хотим выполнить запрос where site_id,site_hash in ( содержимое файла ) 

Тогда отправляем созданный файл на сервер базыданных, и указываем имя,формат.

Теперь можем использовать его в запросе.


```php
$file_name_data1 = '/tmp/temp_csv.txt'; // two column file [int,string]
$whereIn = new \ClickHouseDB\WhereInFile();
$whereIn->attachFile(
        $file_name_data1, 
        'namex', 
        ['site_id' => 'Int32', 'site_hash' => 'String'], 
        \ClickHouseDB\WhereInFile::FORMAT_CSV);
$result = $db->select($sql, [], $whereIn);
```

Запрос 

```sql

SELECT site_id, group, SUM(views) as views FROM aggr.summing_url_views
WHERE 
   event_date = today() 
   AND ( 
      site_id IN (SELECT site_id FROM namex)
   )
GROUP BY site_id, group
ORDER BY views DESC
LIMIT 5
```

#### Рабора с результатом запросов Statement


```php
// Count select rows
$statement->count();

// Count all rows
$statement->countAll();

// fetch one row
$statement->fetchOne();

// get extremes min
print_r($statement->extremesMin());

// totals row
print_r($statement->totals());

// result all
print_r($statement->rows());

// totalTimeRequest
print_r($statement->totalTimeRequest());

// raw answer JsonDecode array, for economy memory
print_r($statement->rawData());

// raw curl_info answer 
print_r($statement->responseInfo());

// human size info
print_r($statement->info());

// информация о выполнении запроса 
print_r($result->statistics());
```

#### Результат в виде дерева


Можно получить ассоциатвный массив результата в виде дерева: 

```php
$statement = $db->select('
    SELECT event_date, site_key, sum(views), avg(views) 
    FROM summing_url_views 
    WHERE site_id < 3333 
    GROUP BY event_date, url_hash 
    WITH TOTALS
');

print_r($statement->rowsAsTree('event_date.site_key'));

/*
(
    [2016-07-18] => Array
        (
            [HASH2] => Array
                (
                    [event_date] => 2016-07-18
                    [url_hash] => HASH2
                    [sum(views)] => 12
                    [avg(views)] => 12
                )
            [HASH1] => Array
                (
                    [event_date] => 2016-07-18
                    [url_hash] => HASH1
                    [sum(views)] => 22
                    [avg(views)] => 22
                )
        )
)
*/

```

#### Результат запроса, напрямую в файл 

Бывает необходимо, результат запроса SELECT записать файл - для дольнейшего импорта другой базой данных. 

Можно выполнить запрос SELECT и не разбирая результат средствами PHP, чтобы секономить ресурсы, напряую записать файл. 
   
   
Используем класc : `WriteToFile(имя_файла,перезапись,$format)`   

```php   
$WriteToFile=new ClickHouseDB\WriteToFile('/tmp/_0_select.csv.gz');
$WriteToFile->setFormat(ClickHouseDB\WriteToFile::FORMAT_TabSeparatedWithNames);
// $WriteToFile->setGzip(true);// cat /tmp/_0_select.csv.gz | gzip -dc > /tmp/w.result
$statement=$db->select('select * from summing_url_views',[],null,$WriteToFile);
print_r($statement->info());
```

При использовании WriteToFile результат запроса будет пустым, т/к парсинг не производится. 
И `$statement->count() и $statement->rows()` пустые. 
 
Для проверики можно получить размер результирующего файла: 
```php
echo $WriteToFile->size(); 
``` 

При указании setGzip(true) - создается gz файл, но у которого отсутствует crc запись, и его распаковка будет с ошибкой проверки crc.
  
Так же возможна асинхронное запись в файл: 
 
```php
$db->selectAsync('select * from summing_url_views limit 14',[],null,new ClickHouseDB\WriteToFile('/tmp/_3_select.tab',true,'TabSeparatedWithNames'));
$db->selectAsync('select * from summing_url_views limit 35',[],null,new ClickHouseDB\WriteToFile('/tmp/_4_select.tab',true,'TabSeparated'));
$db->selectAsync('select * from summing_url_views limit 55',[],null,new ClickHouseDB\WriteToFile('/tmp/_5_select.csv',true,ClickHouseDB\WriteToFile::FORMAT_CSV));
$db->executeAsync();
``` 



Реализация через установку CURLOPT_FILE: 

```php
$curl_opt[CURLOPT_FILE]=$this->resultFileHandle;
// Если указан gzip, дописываем в начало файла : 
 "\x1f\x8b\x08\x00\x00\x00\x00\x00"
// и вешаем на указатель файла: 
  $params = array('level' => 6, 'window' => 15, 'memory' => 9);
  stream_filter_append($this->resultFileHandle, 'zlib.deflate', STREAM_FILTER_WRITE, $params);
```

###  Кластер 

Допустим есть серверный конфиг в ansible  который создает кластеры: 
 ```
 ansible.cluster1.yml
    - name: "pulse"
      shards:
        - { name: "01", replicas: ["clickhouse63.smi2", "clickhouse64.smi2"]}
        - { name: "02", replicas: ["clickhouse65.smi2", "clickhouse66.smi2"]}
    - name: "sharovara"
      shards:
        - { name: "01", replicas: ["clickhouse63.smi2"]}
        - { name: "02", replicas: ["clickhouse64.smi2"]}
        - { name: "03", replicas: ["clickhouse65.smi2"]}
        - { name: "04", replicas: ["clickhouse66.smi2"]}
    - name: "repikator"
      shards:
        - { name: "01", replicas: ["clickhouse63.smi2", "clickhouse64.smi2","clickhouse65.smi2", "clickhouse66.smi2"]}
    - name: "sharovara3x"
      shards:
        - { name: "01", replicas: ["clickhouse64.smi2"]}
        - { name: "02", replicas: ["clickhouse65.smi2"]}
        - { name: "03", replicas: ["clickhouse66.smi2"]}
    - name: "repikator3x"
      shards:
        - { name: "01", replicas: ["clickhouse64.smi2","clickhouse65.smi2", "clickhouse66.smi2"]}
```

Создаем класс для работы с кластером: 
```
$cl = new ClickHouseDB\Cluster(
  ['host'=>'allclickhouse.smi2','port'=>'8123','username'=>'x','password'=>'x']
);
```
Где в DNS записи `allclickhouse.smi2` перечисленны все IP адреса всех серверов:  

`clickhouse64.smi2 , clickhouse65.smi2 , clickhouse66.smi2 , clickhouse63.smi2`


Установим время за которое можно подключиться ко всем нодам:
```
$cl->setScanTimeOut(2.5); // 2500 ms
```
Проверяем что состояние рабочее, в данный момент происходит асинхронное подключение ко всем серверам  
```
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}
```

Как работает проверка: 
* Установленно соединение со всеми сервера перечисленным в DNS записи  
* Проверка таблицы system.replicas что всё хорошо 
  * not is_readonly 
  * not is_session_expired
  * not future_parts > 20
  * not parts_to_check > 10
  * not queue_size > 20 
  * not inserts_in_queue > 10 
  * not log_max_index - log_pointer > 10
  * not total_replicas < 2 ( зависит от использумого cluster )
  * active_replicas < total_replicas
 

Получаем список всех cluster 
```
print_r($cl->getClusterList());
// result 
//    [0] => pulse
//    [1] => repikator
//    [2] => repikator3x
//    [3] => sharovara
//    [4] => sharovara3x
```


Узнаем список node(ip) и кол-во shard,replica

```
foreach (['pulse','repikator','sharovara','repikator3x','sharovara3x'] as $name)
{
    print_r($cl->getClusterNodes($name));
    echo "> $name , count shard   = ".$cl->getClusterCountShard($name)." ; count replica = ".$cl->getClusterCountReplica($name)."\n";
}
//result: 
// pulse , count shard = 2 ; count replica = 2
// repikator , count shard = 1 ; count replica = 4
// sharovara , count shard = 4 ; count replica = 1
// repikator3x , count shard = 1 ; count replica = 3
// sharovara3x , count shard = 3 ; count replica = 1

```


Получаем список node по имени кластера, или из sharded таблиц: 

```php
$nodes=$cl->getNodesByTable('shara.adpreview_body_views_sharded');
$nodes=$cl->getClusterNodes('sharovara');
```

Пример получениея размера таблиц или всех таблиц на выбранных нодах:
```php
foreach ($nodes as $node)
{
    echo "$node > \n";
    print_r($cl->client($node)->tableSize('adpreview_body_views_sharded'));
    print_r($cl->client($node)->tablesSize());
}
```


## Миграции в кластере

Перенесено в отдельный проект https://github.com/smi2/phpMigrationsClickhouse


Отправляем запрос на сервера выбранного кластера в виде миграции,
Если хоть на одном происходит ошибка, выполняем откат запросов

Перед выполнение миграции, каждый узел кластера еще раз проверяется на доступность через `ping()`. 


```php

$mclq=new ClickHouseDB\Cluster\Migration($cluster_name);
$mclq->addSqlUpdate('CREATE DATABASE IF NOT EXISTS cluster_tests');
$mclq->addSqlDowngrade('DROP DATABASE IF EXISTS shara');


if (!$cl->sendMigration($mclq))
{
    throw new Exception('sendMigration error='.$cl->getError());
}

```


