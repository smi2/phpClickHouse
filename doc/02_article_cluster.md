# Подключение к кластеру ClickHouse из PHP

* Что такое кластер ?
* Что такое реплики ?
*


(http://uk0.us/2011/01/simple-raw-sql-migrations-for-kohana-3/)

### Вступление. Немного о масштабировании Clickhouse


Сдесь будет очень коротко что такое кластер и репликация+шардирование.
Нет смысла переписывать документацию. 


имеется база данных, в которую осуществляется запись и чтение данных. 
В динамично растущих системах, объемы данных, как правило, быстро 
увеличиваются и рано или поздно можно столкнуться с проблемой, 
когда текущих ресурсов машины будет не хватать для нормальной работы.

Для решения этой проблемы применяют масштабирование. 
Масштабирование бывает 2-х видов — горизонтальное и вертикальное. 

Вертикальное масштабирование — наращивание мощностей одной машины — добавление CPU, RAM, HDD. 
Горизонтальное масштабирование — добавление новых машин к существующим и распределение данных между ними.


Второй случай более сложен в конфигурации, но имеет ряд преимуществ: 
Теоретически бесконечное масштабирование (машин можно поставить сколько угодно)
Бо́льшая безопасность данных (только при использовании репликации) — машины могут располагаться в разных дата центрах (при падении одной из них, останутся другие)






Для подключения к кластеру используем отдельный класс


# Отправка запросов в кластер, реализация миграций на PHP







### Примеры миграций

Мы используем (mybatis):[http://www.mybatis.org/migrations/]



https://habrahabr.ru/post/315254/


Почему миграции это круто ?
Допустим у вас таблица из 50 колонок , а елси их 200 или 500 ?
Вы ведете отдельную документацию по каждой колонке?
Вы помните когда каждую колонку добавил ?




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

###  Некоторые примеры развертки Clickhouse

https://clickhouse.yandex/reference_ru.html#Distributed
Движок Distributed -  не хранит данные самостоятельно, а позволяет обрабатывать запросы распределённо, на нескольких серверах.
Чтение автоматически распараллеливается. 


https://clickhouse.yandex/reference_ru.html#Репликация данных

Репликация данных - Движок Replicated* 
Репликация никак не связана с шардированием. На каждом шарде репликация работает независимо.
Репликация является опциональной возможностью. Для использования репликации, укажите в конфигурационном файле адреса ZooKeeper кластера.



Допустим есть серверный конфиг в ansible  который создает кластеры:
Не стоит использовать в продакшене кластер: sharovara - это для теста и пример сделан в этом кластере нет репликации, т/е если вылетает одна из нод
вытеряете 1/4 данных.

pulse - это две копии на две шарды
sharovara - это 4е шары
repikator - 4е реплики - максимальная надежность для параноии.



```xml
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

```


(Засунуть в споллер)
Или конфигрурация в XML :

```xml

	<remote_servers>
<repikator>

		<shard>
			<replica>
				<host>clickhouse63.smi2</host>
			</replica>
			<replica>
				<host>clickhouse64.smi2</host>
			</replica>
			<replica>
				<host>clickhouse65.smi2</host>
			</replica>
			<replica>
				<host>clickhouse66.smi2</host>
			</replica>
		</shard>
		</repikator>
<pulse>
		<!-- 01 -->
		<shard>
                <replica>
                    <host>clickhouse63.smi2</host>
                </replica>
                <replica>
                    <host>clickhouse64.smi2</host>
                </replica>
		</shard>
		<!-- 02 -->
		<shard>
                <replica>
                    <host>clickhouse65.smi2</host>
                </replica>
                <replica>
                    <host>clickhouse66.smi2</host>
                </replica>
		</shard>
</pulse>
<sharovara>
		<!-- 01 -->
		<shard>
                <replica>
                    <host>clickhouse63.smi2</host>
                </replica>
		</shard>
		<!-- 02 -->
		<shard>
                <replica>
                    <host>clickhouse64.smi2</host>
                </replica>
		</shard>
		<!-- 03 -->
		<shard>
			<replica>
				<host>clickhouse65.smi2</host>
			</replica>
		</shard>
		<!-- 04 -->
		<shard>
			<replica>
				<host>clickhouse66.smi2</host>
			</replica>
		</shard>
		</sharovara>
		
```

Создаем класс для работы с кластером:

```php
$cl = new ClickHouseDB\Cluster(
  ['host'=>'allclickhouse.smi2','port'=>'8123','username'=>'x','password'=>'x']
);
```
Где в DNS записи `allclickhouse.smi2` перечисленны все IP адреса всех серверов:

`clickhouse64.smi2 , clickhouse65.smi2 , clickhouse66.smi2 , clickhouse63.smi2`


Установим время за которое можно подключиться ко всем нодам:
```php
$cl->setScanTimeOut(2.5); // 2500 ms
```
Проверяем что состояние рабочее, в данный момент происходит асинхронное подключение ко всем серверам
```php
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}
```

Если не запрашивать последние 4 столбца (log_max_index, log_pointer, total_replicas, active_replicas), то таблица работает быстро.





Как работает проверка:
(https://clickhouse.yandex/reference_ru.html#system.replicas)
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


future_parts:       количество кусков с данными, которые появятся в результате INSERT-ов или слияний, которых ещё предстоит сделать
parts_to_check:     количество кусков с данными в очереди на проверку Кусок помещается в очередь на проверку, если есть подозрение, что он может быть битым.
log_max_index:      максимальный номер записи в общем логе действий
log_pointer:        максимальный номер записи из общего лога действий, которую реплика скопировала в свою очередь для выполнения, плюс единица
Если log_pointer сильно меньше log_max_index, значит что-то не так.
total_replicas:     общее число известных реплик этой таблицы
active_replicas:    число реплик этой таблицы, имеющих сессию в ZK; то есть, число работающих реплик
Если запрашивать все столбцы, то таблица может работать слегка медленно, так как на каждую строчку делается несколько чтений из ZK.
Если не запрашивать последние 4 столбца (log_max_index, log_pointer, total_replicas, active_replicas), то таблица работает быстро.






Получаем список всех cluster
```php
print_r($cl->getClusterList());
// result
//    [0] => pulse
//    [1] => repikator
//    [2] => repikator3x
//    [3] => sharovara
//    [4] => sharovara3x
```


Узнаем список node(ip) и кол-во shard,replica

```php
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



# Выгружайте сырые данные из Метрики через Logs API


