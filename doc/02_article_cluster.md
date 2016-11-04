

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

