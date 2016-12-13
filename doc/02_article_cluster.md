# Масштабирование ClickHouse и отправка запросов из PHP в кластер

В предыдущей [статье](https://habrahabr.ru/company/smi2/blog/314558/) мы поделились своим опытом внедрения и использования СУБД ClickHouse в компании [СМИ2](https://smi2.net/). В текущей статье затронем вопросы масштабирования, когда с увеличением объема анализируемых данных и ростом нагрузки данные уже не могут храниться и обрабатываться в рамках одного физического сервера. А также поделимся инструментом миграции [DDL](https://en.wikipedia.org/wiki/Data_definition_language)-запросов.

[ClickHouse](https://clickhouse.yandex/) специально проектировался для работы в кластерах, расположенных в разных дата-центрах. Масштабируется СУБД линейно до сотен узлов. На момент написания статьи [Яндекс.Метрика](https://metrika.yandex.ru/) - это кластер из более чем 400 узлов. 

"Из коробки" ClickHouse предоставляет шардирование и репликацию, которые могут гибко настраиваться отдельно для каждой таблицы. Для обеспечения работы реплицирования требуется [Apache ZooKeeper](https://zookeeper.apache.org/) (рекомендуется использовать версию 3.4.5+). Для обеспечения более высокой надежности мы используем ZK-кластер (ансамбль) из 5 узлов. Следует выбирать нечетное число ZK-узлов, например, 3 или 5, чтобы обеспечить кворум. Также отметим, что ZK не используется в операциях SELECT, а применяется, например, в ALTER-запросах для изменений столбцов, сохраняя инструкции для каждой из реплик.   

### Шардирование

В ClickHouse шардирование позволяет записывать и хранить порции данных распределенно в кластере, и обрабатывать (читать) данные параллельно на всех узлах кластера, увеличивая throughput и уменьшая latency. Например, в запросах с GROUP BY ClickHouse выполнит агрегирование на удаленных узлах и передаст узлу-инициатору запроса промежуточные состояния агрегатных функций, где они буду доагрегированы.

Для шардирования используется специальный движок: [Distributed](https://clickhouse.yandex/reference_ru.html#Distributed), который не хранит данные, а делегирует SELECT-запросы на шардированные таблицы (таблицы, содержащие порции данных) с последующей обработкой полученных данных. Запись данных в шарды может выполняться в двух режимах: через Distributed-таблицу и необязательный ключ шардирования или непосредственно в шардированные таблицы, из которых далее будут читаться данные через Distributed-таблицу. Рассмотрим эти режимы более подробно. 
       
В простом случае данные записываются в Distributed-таблицу по ключу шардирования. В простейшем случае ключом шардирования может быть случайное число, т.е. результат вызова функции [rand()](https://clickhouse.yandex/reference_ru.html#rand). Однако, в качестве ключа шардирования рекомендуется брать значение хеш-функции от поля в таблице, которое позволит, с одной стороны, локализовать небольшие наборы данных на одном шарде, а с другой, обеспечит достаточно ровное распределение таких наборов по разным шардам в кластере. Например, идентификатор сессии (sess_id) пользователя позволит локализовать показы страниц одному пользователю на одном шарде, а с другой стороны сессии разных пользователей будут распределены равномерно по всем шардам в кластере. При условии, что значения поля sess_id будут иметь хорошее распределение. Ключ шардирования может быть нечисловой или составной, тогда можно применять втроенную хеширующую функцию [cityHash64](https://clickhouse.yandex/reference_ru.html#cityHash64). В данном режиме данные, записываемые на один из узлов кластера, по ключу шардирования будут перенаправляться на нужные шарды автоматически, правда, увеличивая трафик.
 
Более сложный способ заключается в том, чтобы снаружи ClickHouse вычислять нужный шард и выполнять запись напрямую в шардированную таблицу. Сложность здесь обусловлена тем, что нужно знать набор доступных узлов-шардов. Однако, запись становится более эффективной и механизм шардирования (определения нужного шарда) может быть более гибким.

### Репликация

ClickHouse поддерживает [репликацию](https://clickhouse.yandex/reference_ru.html#Репликация%20данных) данных, обеспечивая целостность данных на репликах. Для репликации данных используются специальные движки MergeTree-семейства:

* ReplicatedMergeTree
* ReplicatedCollapsingMergeTree
* ReplicatedAggregatingMergeTree
* ReplicatedSummingMergeTree

Репликация часто применяется с шардированием. Например, кластер из 6-узлов может содержать 3 шарда по 2 реплики. Следует отметить, что репликация не зависит от механизмов шардирования, и работает на уровне отдельных таблиц.
  
Запись данных может выполняться в любую из таблиц-реплик, ClickHouse выполняет автоматическую синхронизацию данных между всеми репликами.

### Инструмент миграции DDL-запросов

На момент написания статьи ClickHouse имеет ряд особенностей (ограничений) связанных с DDL-запросами. [Цитата](https://clickhouse.yandex/reference_ru.html#Репликация данных):

> Реплицируются INSERT, ALTER (см. подробности в описании запроса ALTER). Реплицируются сжатые данные, а не тексты запросов. Запросы CREATE, DROP, ATTACH, DETACH, RENAME не реплицируются - то есть, относятся к одному серверу. Запрос CREATE TABLE создаёт новую реплицируемую таблицу на том сервере, где выполняется запрос; а если на других серверах такая таблица уже есть - добавляет новую реплику. Запрос DROP TABLE удаляет реплику, расположенную на том сервере, где выполняется запрос. Запрос RENAME переименовывает таблицу на одной из реплик - то есть, реплицируемые таблицы на разных репликах могут называться по разному.

Когда количество узлов кластера становится большим, то управление кластером становится неудобным. В результате мы создали простой и достаточно функциональный инструмент для миграции DDL-запросов в ClickHouse-кластер. И работу с кластером продемонстрируем на примере.  


## Пример конфигурации Clickhouse


Для статьи и тестов мы сделаем, несколько конфигураций, надеюсь читатель не будет их использовать в продакшен системе, за исключением одной конфигурации.

Когда у нас один ClickHouse сервер пусть называется `ch63.smi2`, все предельно просто, но мы захотели добавить несколько - для обеспеспечения максимальной безопасности.

И так делаем : один сервер `ch63.smi2` и три копии данных `ch64.smi2 , ch65.smi2 , ch65.smi2`  - максимальная параноидальность -  и назовем такую конфигурацию = *repikator*


Тогда в конфигурационном CH файле опишим:

```xml
<repikator>
   <shard>
       <replica>
           <host>ch63.smi2</host>
       </replica>
       <replica>
           <host>ch64.smi2</host>
       </replica>
       <replica>
           <host>ch65.smi2</host>
       </replica>
       <replica>
           <host>ch66.smi2</host>
       </replica>
   </shard>
</repikator>     
```




![Только реплики ](https://api.monosnap.com/rpc/file/download?id=BahALelyOJWu7ordZAFq6wvCaz6m3J)

```sql

CREATE DATABASE IF NOT EXISTS dbrepikator
;

CREATE TABLE IF NOT EXISTS dbrepikator.anysumming_repl_sharded (
    event_date Date DEFAULT toDate(event_time),
    event_time DateTime DEFAULT now(),
    body_id Int32,
    views Int32
) ENGINE = ReplicatedSummingMergeTree('/clickhouse/tables/{repikator_replica}/dbrepikator/anysumming_repl_sharded', '{replica}', event_date, (event_date, event_time, body_id), 8192)
;

CREATE TABLE IF NOT EXISTS  dbrepikator.anysumming_repl AS test.anysumming_repl_sharded
      ENGINE = Distributed( repikator, dbrepikator, anysumming_repl_sharded , rand() )

```


Ок в этом примере мы достигли максимальной безопасности, но данные занимают очень много места
Вставка данных идет в таблицу `anysumming_repl`


#### Конфигурация только из шардов

Создадим конфигурацию `sharovara` состоящую только из шардов - без реплик - ну вдруг вы такой рисковый читатель и доверяете своим HDD
![Конфигруция только из шардов ](https://api.monosnap.com/rpc/file/download?id=X7lbGzFQ9HriQQ9QrlaLZRMPbQ4Sx1)
```xml
<sharovara>
   <shard>
       <replica>
           <host>ch63.smi2</host>
       </replica>
   </shard>
   <shard>
       <replica>
           <host>ch64.smi2</host>
       </replica>
   </shard>
   <shard>
       <replica>
           <host>ch65.smi2</host>
       </replica>
   </shard>
   <shard>
       <replica>
           <host>ch66.smi2</host>
       </replica>
   </shard>
</sharovara>
```

Создадим таблицу :

```sql

CREATE DATABASE IF NOT EXISTS testshara 
;
CREATE TABLE IF NOT EXISTS testshara.anysumming_sharded (
    event_date Date DEFAULT toDate(event_time),
    event_time DateTime DEFAULT now(),
    body_id Int32,
    views Int32
) ENGINE = ReplicatedSummingMergeTree('/clickhouse/tables/{sharovara_replica}/sharovara/anysumming_sharded_sharded', '{replica}', event_date, (event_date, event_time, body_id), 8192)
;
CREATE TABLE IF NOT EXISTS  testshara.anysumming AS testshara.anysumming_sharded
      ENGINE = Distributed( sharovara, testshara, anysumming_sharded , rand() )

```

Получается если мы пишем в таблицу `anysumming` то данные пишутся сразу на все сервера и размазываются равномерно ( веса серверов выходят за рамки статьи)



### Нормальная конфигурация

И так давайте "без фанатизма" ->  создаем нормальную конфигурацию назовем ее `pulse`, дано 4е сервера сделаем одну копию данных и по ?один? шард
![Схема ](https://api.monosnap.com/rpc/file/download?id=HZblGQjLnOU6WlprWxb8W5FyixNlfY)


```xml


<pulse>
   <shard>
       <replica>
           <host>ch63.smi2</host>
       </replica>
       <replica>
           <host>ch64.smi2</host>
       </replica>
   </shard>
   <shard>
       <replica>
           <host>ch65.smi2</host>
       </replica>
       <replica>
           <host>ch66.smi2</host>
       </replica>
   </shard>
</pulse>


```


Получается :

ch63 -> имеет копию данных на ch64

ch65 -> имеет копию данных на ch66

И данные равномерно записываются в пропорции 50 на 50 на ch63 и ch65 сервера.



```sql

CREATE DATABASE IF NOT EXISTS dbpulse 
;

CREATE TABLE IF NOT EXISTS dbpulse.normal_summing_sharded (
    event_date Date DEFAULT toDate(event_time),
    event_time DateTime DEFAULT now(),
    body_id Int32,
    views Int32
) ENGINE = ReplicatedSummingMergeTree('/clickhouse/tables/{pulse_replica}/pulse/normal_summing_sharded', '{replica}', event_date, (event_date, event_time, body_id), 8192)
;

CREATE TABLE IF NOT EXISTS  dbpulse.normal_summing AS dbpulse.normal_summing_sharded
      ENGINE = Distributed( pulse, dbpulse, normal_summing_sharded , rand() )

```



#### Итог конфигурации


Если описать все написанное выше в виде ansible конфига, для наглядности ( мы планируем выложить его OpenSource )


```xml
ansible.cluster1.yml
   - name: "pulse"
     shards:
       - { name: "01", replicas: ["ch63.smi2", "ch64.smi2"]}
       - { name: "02", replicas: ["ch65.smi2", "ch66.smi2"]}
   - name: "sharovara"
     shards:
       - { name: "01", replicas: ["ch63.smi2"]}
       - { name: "02", replicas: ["ch64.smi2"]}
       - { name: "03", replicas: ["ch65.smi2"]}
       - { name: "04", replicas: ["ch66.smi2"]}
   - name: "repikator"
     shards:
       - { name: "01", replicas: ["ch63.smi2", "ch64.smi2","ch65.smi2", "ch66.smi2"]}


```


Не стоит использовать в продакшене кластер:

sharovara - это для теста и пример сделан в этом кластере нет репликации, т/е если вылетает одна из нод вы теряете 1/4 данных.


`pulse` - это две копии на две шарды

`sharovara` - это 4е шары

`repikator` - 4е реплики - максимальная надежность для паранойи.






### Перешардирование CH кластера


Исходя из общения с разработчиками и из документации - это боль и страдание)








# Отправка запросов из PHP в кластер


И так вы поставили наш php драйвер, для подключения к кластеру используем отдельный класс `ClickHouseDB\Cluster`


```php

$cl = new ClickHouseDB\Cluster(
 ['host'=>'allclickhouse.smi2','port'=>'8123','username'=>'x','password'=>'x']
);

```


Где в DNS записи `allclickhouse.smi2` перечислены все IP адреса всех серверов: `ch64.smi2 , ch65.smi2 , ch66.smi2 , ch63.smi2`


Тогда это позволят использую DNS_RoundRobing - обезопасить себя от выпадения одной из нод.


При использовании кластера класса - драйвер подключается ко всем нодам из списка IP адресов основного имени, и отправляет параллельно на каждую ноду `ping` запрос. 

Установим максимальное время за которое можно подключиться ко всем нодам:
```php

$cl->setScanTimeOut(2.5); // 2500 ms

```


Проверяем что состояние рабочее,
```php

if (!$cl->isReplicasIsOk())
{
   throw new Exception('Replica state is bad , error='.$cl->getError());
}

```


Как работает проверка, что состояние кластера нормальное : 

* Установлено соединение со всеми сервера перечисленным в DNS записи
* Отправляем запросы в каждый доступный сервер, в системные таблицы `system.replicas` и `system.clusters`, [Например, так можно проверить, что всё хорошо](https://clickhouse.yandex/reference_ru.html#system.replicas) 


В данном случае мы запрашиваем все столбцы, то таблица может работать медленно, так как на каждую строчку делается несколько чтений из ZK.
Если не запрашивать последние 4 столбца (log_max_index, log_pointer, total_replicas, active_replicas), то таблица работает быстро.

Чтобы не запрашивать каждый раз ZK,т/е не запрашивать последние 4 столбца :
```php

$cl->setSoftCheck(true);

```

Получаем список всех cluster
```php
print_r($cl->getClusterList());
// result
//    [0] => pulse
//    [1] => repikator
//    [2] => sharovara
```


Узнаем список node(ip) и кол-во шардов и количество реплик


```php

foreach (['pulse','repikator','sharovara','repikator3x','sharovara3x'] as $name)
{
   print_r($cl->getClusterNodes($name));
   echo "> $name , count shard   = ".$cl->getClusterCountShard($name)." ; count replica = ".$cl->getClusterCountReplica($name)."\n";
}

//result:
//>  pulse , count shard = 2 ; count replica = 2
//>  repikator , count shard = 1 ; count replica = 4
//>  sharovara , count shard = 4 ; count replica = 1

```


Получаем список node по имени кластера, или из sharded таблиц:


```php

$nodes=$cl->getNodesByTable('sharovara.body_views_sharded');

$nodes=$cl->getClusterNodes('sharovara');

```


Пример получения размера таблиц или всех таблиц через отправку запроса на каждую ноду:


```php

foreach ($nodes as $node)
{
   echo "$node > \n";
   print_r($cl->client($node)->tableSize('adpreview_body_views_sharded'));
   print_r($cl->client($node)->tablesSize());
}


// сокращенный вариант
$cl->getSizeTable('dbName.tableName');

```


Список таблиц кластера :
```php

$cl->getTables()

```


### Определение лидера в кластере


```php

$cl->getMasterNodeForTable('dbName.tableName') // node have is_leader=1

```


Это позволяет понять какая нода является лидером, т/е у нее установлен признак `is_leader=1` для отправки на нее запроса, на удаление данных или для изменения структуры.


Для очистки всех данных в одной таблицы во всем кластере


```php

$cl->truncateTable('dbName.tableName')`

```


## Миграции


Мы используем [mybatis](http://www.mybatis.org/migrations/) для нашей MySQL базы данных.
Есть много других красивых продуктов для реализации миграции, не которые из них: [phinx](https://phinx.org), [Liquibase](http://liquibase.org). 




А зачем эти миграции нужны и что это вообще такое?

* [Версионная миграция структуры базы данных: основные подходы](https://habrahabr.ru/post/121265/)
* [Простые миграции с PHPixie Migrate](https://habrahabr.ru/post/315254/)
* [Управление скриптами миграции или MyBatis Scheme Migration Extended](https://habrahabr.ru/post/129290/)




Вопросы читателю:

* Допустим у вас таблица из 50 колонок , а если их 200 или 500 ?
* Вы ведете отдельную документацию по каждой колонке ?
* Вы помните когда каждую колонку добавил ?
* Вы согласовываете alter table запросы внутри команды ?


Я ( мы ) начали реализовывать проект позволяющий осуществлять миграции в CH, [phpMigrationsClickhouse](https://github.com/smi2/phpMigrationsClickhouse)
проект находится в состоянии альфа релиза и после последнего митапа - когда CH возьмет на себя раскатывания изменений по всем нодам -> проект остановился.

Данная статья содержит опрос - пожалуйста проголосуйте: 

* Убери с глаз ваш велосипед на php
* Стоит подождать реализации в самом CH и продолжить реализацию
* Мне интересно решение - т/к мы делаем сами костыльные решения для этого.



## phpMigrationsClickhouse tool




Данный инструмент, позволяет оправлять запросы на сервера выбранного кластера в виде миграции, если происходит ошибка на одном из серверов - выполняем откат запросов. 

Перед выполнение каждой миграции, каждый узел кластера еще раз проверяется на доступность через `ping()`.


```php


$mclq=new ClickHouseDB\Cluster\Migration($cluster_name);

$mclq->addSqlUpdate('CREATE DATABASE IF NOT EXISTS cluster_tests');

$mclq->addSqlDowngrade('DROP DATABASE IF EXISTS cluster_tests');


if (!$cl->sendMigration($mclq))
{
   throw new Exception('sendMigration error='.$cl->getError());
}


```


""" ДОПИСАТЬ С ПРИМЕРОМ из readme.md """




