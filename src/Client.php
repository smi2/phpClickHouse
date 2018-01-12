<?php

namespace ClickHouseDB;

use ClickHouseDB\Transport\Http;

/**
 * Class Client
 * @package ClickHouseDB
 */
class Client
{
    /**
     * @var Http
     */
    private $_transport = false;

    /**
     * @var string
     */
    private $_connect_username = false;

    /**
     * @var string
     */
    private $_connect_password = false;

    /**
     * @var string
     */
    private $_connect_host = false;

    /**
     * @var int
     */
    private $_connect_port = false;

    /**
     * @var bool
     */
    private $_connect_user_readonly=false;
    /**
     * @var array
     */
    private $_support_format=['TabSeparated','TabSeparatedWithNames','CSV','CSVWithNames'];
    /**
     * Client constructor.
     * @param $connect_params
     * @param array $settings
     */
    public function __construct($connect_params, $settings = [])
    {
        if (!isset($connect_params['username'])) {
            throw  new \InvalidArgumentException('not set username');
        }

        if (!isset($connect_params['password'])) {
            throw  new \InvalidArgumentException('not set password');
        }

        if (!isset($connect_params['port'])) {
            throw  new \InvalidArgumentException('not set port');
        }

        if (!isset($connect_params['host'])) {
            throw  new \InvalidArgumentException('not set host');
        }

        if (isset($connect_params['settings']) && is_array($connect_params['settings'])) {
            if (empty($settings)) {
                $settings = $connect_params['settings'];
            }
        }

        $this->_connect_username    = $connect_params['username'];
        $this->_connect_password    = $connect_params['password'];
        $this->_connect_port        = $connect_params['port'];
        $this->_connect_host        = $connect_params['host'];


        // init transport class
        $this->_transport = new Http(
            $this->_connect_host,
            $this->_connect_port,
            $this->_connect_username,
            $this->_connect_password
        );


        $this->_transport->addQueryDegeneration(new \ClickHouseDB\Query\Degeneration\Bindings());

        // apply settings to transport class
        $this->settings()->database('default');
        if (sizeof($settings)) {
            $this->settings()->apply($settings);
        }


        if (isset($connect_params['readonly']))
        {
            $this->setReadOnlyUser($connect_params['readonly']);
        }

        if (isset($connect_params['https']))
        {
            $this->https($connect_params['https']);
        }




    }

    /**
     * если у пользовалетя установленно только чтение в конфиге
     *
     * @param $flag
     */
    public function setReadOnlyUser($flag)
    {
        $this->_connect_user_readonly=$flag;
        $this->settings()->setReadOnlyUser($this->_connect_user_readonly);
    }
    /**
     * Очистить пред обработку запроса [шаблонизация]
     *
     * @return bool
     */
    public function cleanQueryDegeneration()
    {
        return $this->_transport->cleanQueryDegeneration();
    }

    /**
     * Добавить пред обработку запроса
     *
     * @param Query\Degeneration $degeneration
     * @return bool
     */
    public function addQueryDegeneration(Query\Degeneration $degeneration)
    {
        return $this->_transport->addQueryDegeneration($degeneration);
    }

    /**
     * Замена :var в запросе
     *
     * @return bool
     */
    public function enableQueryConditions()
    {
        return $this->_transport->addQueryDegeneration(new \ClickHouseDB\Query\Degeneration\Conditions());
    }
    /**
     * Set connection host
     *
     * @param $host
     */
    public function setHost($host)
    {

        if (is_array($host))
        {
            $host=array_rand(array_flip($host));
        }

        $this->_connect_host=$host;
        $this->transport()->setHost($host);
    }

    /**
     * Таймаут
     *
     * @param $timeout
     * @return Settings
     */
    public function setTimeout($timeout)
    {
       return $this->settings()->max_execution_time($timeout);
    }

    /**
     * Таймаут
     *
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->settings()->getTimeOut();
    }

    /**
     * Количество секунд ожидания
     *
     * @param int $connectTimeOut
     */
    public function setConnectTimeOut($connectTimeOut)
    {
        $this->transport()->setConnectTimeOut($connectTimeOut);
    }

    /**
     * Количество секунд ожидания
     *
     * @return int
     */
    public function getConnectTimeOut()
    {
        return $this->transport()->getConnectTimeOut();
    }


    /**
     * transport
     *
     * @return Http
     */
    public function transport()
    {
        if (!$this->_transport) {
            throw  new \InvalidArgumentException('Empty transport class');
        }
        return $this->_transport;
    }

    /**
     * @return string
     */
    public function getConnectHost()
    {
        return $this->_connect_host;
    }

    /**
     * @return string
     */
    public function getConnectPassword()
    {
        return $this->_connect_password;
    }

    /**
     * @return string
     */
    public function getConnectPort()
    {
        return $this->_connect_port;
    }

    /**
     * @return string
     */
    public function getConnectUsername()
    {
        return $this->_connect_username;
    }

    /**
     * transport
     *
     * @return Http
     */
    public function getTransport()
    {
        return $this->_transport;
    }


    /**
     * Режим отладки CURL
     *
     * @return mixed
     */
    public function verbose()
    {
        return $this->transport()->verbose(true);
    }

    /**
     * @return Settings
     */
    public function settings()
    {
        return $this->transport()->settings();
    }

    /**
     * @return $this
     */
    public function useSession($useSessionId=false)
    {
        if (!$this->settings()->getSessionId())
        {
            if (!$useSessionId)
            {
                $this->settings()->makeSessionId();
            }
            else
            {
                $this->settings()->session_id($useSessionId);
            }

        }
        return $this;
    }
    /**
     * @return string
     */
    public function getSession()
    {
        return $this->settings()->getSessionId();
    }

    /**
     * Запрос на запись CREATE/DROP
     *
     * @param $sql
     * @param array $bindings
     * @param bool $exception
     * @return Statement
     */
    public function write($sql, $bindings = [], $exception = true)
    {
        return $this->transport()->write($sql, $bindings, $exception);
    }

    /**
     * @param $db
     * @return $this
     */
    public function database($db)
    {
        $this->settings()->database($db);
        return $this;
    }

    /**
     * Логгировать запросы и писать лог в системную таблицу. <database>system</database> <table>query_log</table>
     *
     * @param bool $flag
     * @return $this
     */
    public function enableLogQueries($flag = true)
    {
        $this->settings()->set('log_queries',intval($flag));
        return $this;
    }

    /**
     * Сжимать результат, если клиент по HTTP сказал, что он понимает данные, сжатые методом gzip или deflate
     *
     * @param bool $flag
     * @return $this
     */
    public function enableHttpCompression($flag = true)
    {
        $this->settings()->enableHttpCompression($flag);
        return $this;
    }


    public function https($flag=true)
    {
        $this->settings()->https($flag);
        return $this;
    }

    /**
     * Считать минимумы и максимумы столбцов результата. Они могут выводиться в JSON-форматах.
     *
     * @param bool $flag
     * @return $this
     */
    public function enableExtremes($flag = true)
    {
        $this->settings()->set('extremes',intval($flag));
        return $this;
    }

    /**
     * SELECT
     *
     * @param $sql
     * @param array $bindings
     * @param WhereInFile $whereInFile
     * @param WriteToFile $writeToFile
     * @return Statement
     */
    public function select($sql, $bindings = [], $whereInFile = null, $writeToFile=null)
    {
        return $this->transport()->select($sql, $bindings, $whereInFile,$writeToFile);
    }

    /**
     * Исполнить запросы из очереди
     *
     * @return bool
     */
    public function executeAsync()
    {
        return $this->transport()->executeAsync();
    }

    public function progressFunction($callback)
    {
        if (!is_callable($callback)) throw new \InvalidArgumentException('Not is_callable progressFunction');

        if (!$this->settings()->is('send_progress_in_http_headers'))
        {
            $this->settings()->set('send_progress_in_http_headers', 1);
        }
        if (!$this->settings()->is('http_headers_progress_interval_ms'))
        {
            $this->settings()->set('http_headers_progress_interval_ms', 100);

        }


        $this->transport()->setProgressFunction($callback);
    }

    /**
     * Подготовить запрос SELECT
     *
     * @param $sql
     * @param array $bindings
     * @param WhereInFile $whereInFile
     * @param WriteToFile $writeToFile
     * @return Statement
     */
    public function selectAsync($sql, $bindings = [], $whereInFile = null,$writeToFile=null)
    {
        return $this->transport()->selectAsync($sql, $bindings, $whereInFile,$writeToFile);
    }

    /**
     * SHOW PROCESSLIST
     *
     * @return array
     */
    public function showProcesslist()
    {
        return $this->select('SHOW PROCESSLIST')->rows();
    }

    /**
     * show databases
     *
     * @return array
     */
    public function showDatabases()
    {
        return $this->select('show databases')->rows();
    }

    /**
     * statement = SHOW CREATE TABLE
     *
     * @param $table
     * @return mixed
     */
    public function showCreateTable($table)
    {
        return ($this->select('SHOW CREATE TABLE '.$table)->fetchOne('statement'));
    }

    /**
     * SHOW TABLES
     *
     * @return array
     */
    public function showTables()
    {
        return $this->select('SHOW TABLES')->rowsAsTree('name');
    }

    /**
     * Получить кол-во одновременных запросов
     *
     * @return int
     */
    public function getCountPendingQueue()
    {
        return $this->transport()->getCountPendingQueue();
    }

    /**
     * Insert Array
     *
     * @param $table
     * @param $values
     * @param array $columns
     * @return Statement
     */
    public function insert($table, $values, $columns = [])
    {
        $sql = 'INSERT INTO ' . $table;

        if (0 !== count($columns)) {
            $sql .= ' (' . implode(',', $columns) . ') ';
        }

        $sql .= ' VALUES ';

        foreach ($values as $row) {
            $sql .= ' (' . FormatLine::Insert($row) . '), ';
        }
        $sql = trim($sql, ', ');
        return $this->transport()->write($sql);
    }

    /**
     * Готовит значения для вставки из ассоциативного массива.
     * Может быть вставления одна строка или много строк, но тогда ключи внутри списка массивов должны совпадать (в том числе и по порядку следования)
     *
     * @param array $values - массив column_name=>value (если вставляем одну строку) или список массивов column_name=>value если вставляем много строк
     * @return array - список массивов - 0=>поля, 1=>список массивов значений для вставки
     */
    public function prepareInsertAssocBulk(array $values)
    {
        if (isset($values[0]) && is_array($values[0])){ //случай, когда много строк вставляется
            $preparedFields = array_keys($values[0]);
            $preparedValues = [];
            foreach ($values as $idx => $row){
                $_fields = array_keys($row);
                if ($_fields !== $preparedFields){
                    throw new QueryException("Fields not match: ".implode(',',$_fields)." and ".implode(',', $preparedFields)." on element $idx");
                }
                $preparedValues[] = array_values($row);
            }
        }else{ //одна строка
            $preparedFields = array_keys($values);
            $preparedValues = [array_values($values)];
        }
        return [$preparedFields, $preparedValues];
    }

    /**
     * Вставляет одну или много строк из ассоциативного массива.
     * Если внутри списка массивов значений будет расхождение по ключам (или их порядку) - выбросит исключение.
     *
     * @param string $table - имя таблицы
     * @param array $values - массив column_name=>value (если вставляем одну строку) или список массивов column_name=>value если вставляем много строк
     * @return Statement
     * @throws QueryException
     */
    public function insertAssocBulk($table, array $values)
    {
        list($columns, $vals) = $this->prepareInsertAssocBulk($values);
        return $this->insert($table, $vals, $columns);
    }

    /**
     * insert TabSeparated files
     *
     * @param $table_name
     * @param $file_names
     * @param $columns_array
     * @return mixed
     */
    public function insertBatchTSVFiles($table_name, $file_names, $columns_array)
    {
        return $this->insertBatchFiles($table_name,$file_names,$columns_array,'TabSeparated');
    }
    /**
     *
     *
     * @param $table_name
     * @param $file_names
     * @param $columns_array
     * @param $format string ['TabSeparated','TabSeparatedWithNames','CSV','CSVWithNames']
     * @return array
     */
    public function insertBatchFiles($table_name, $file_names, $columns_array,$format="CSV")
    {
        if (is_string($file_names))
        {
            $file_names=[$file_names];
        }
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        if (!in_array($format,$this->_support_format))
        {
            throw new QueryException('Format not support in insertBatchFiles');
        }

        $result = [];

        foreach ($file_names as $fileName) {
            if (!is_file($fileName) || !is_readable($fileName)) {
                throw  new QueryException('Cant read file: ' . $fileName.' '.(is_file($fileName)?'':' is not file'));
            }

            if (!$columns_array)
            {
                $sql = 'INSERT INTO ' . $table_name . ' FORMAT '.$format;

            }
            else
            {
                $sql = 'INSERT INTO ' . $table_name . ' ( ' . implode(',', $columns_array) . ' ) FORMAT '.$format;

            }
            $result[$fileName] = $this->transport()->writeAsyncCSV($sql, $fileName);
        }

        // exec
        $exec = $this->executeAsync();

        // fetch resutl
        foreach ($file_names as $fileName) {
            if ($result[$fileName]->isError()) {
                $result[$fileName]->error();
            }
        }

        return $result;
    }

    /**
     * @param string $table_name
     * @param array $columns_array
     * @param string $format
     *
     * @return \Curler\Request
     * @internal param $stream
     */
    public function insertBatchStream($table_name, $columns_array,$format="CSV")
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        if (!in_array($format,$this->_support_format))
        {
            throw new QueryException('Format not support in insertBatchFiles');
        }

        if (!$columns_array)
        {
            $sql = 'INSERT INTO ' . $table_name . ' FORMAT '.$format;

        }
        else
        {
            $sql = 'INSERT INTO ' . $table_name . ' ( ' . implode(',', $columns_array) . ' ) FORMAT '.$format;

        }

        return $this->transport()->writeStreamData($sql);
    }


    /**
     * Размер базы
     *
     * @return mixed|null
     */
    public function databaseSize()
    {
        $b = $this->settings()->getDatabase();

        return $this->select('
            SELECT database,formatReadableSize(sum(bytes)) as size
            FROM system.parts
            WHERE active AND database=:database
            GROUP BY database
        ', ['database' => $b])->fetchOne();
    }

    /**
     * Размер таблицы
     *
     * @param $tableName
     * @return mixed
     */
    public function tableSize($tableName)
    {
        $tables = $this->tablesSize();

        if (isset($tables[$tableName])) {
            return $tables[$tableName];
        }

        return null;
    }

    /**
     * @return bool
     */
    public function ping()
    {
        $result = $this->select('SELECT 1 as ping')->fetchOne('ping');
        return ($result == 1);
    }

    /**
     * Размеры таблиц
     *
     * @return array
     */
    public function tablesSize($flatList=false)
    {
        $z=$this->select('
            SELECT table,database,
            formatReadableSize(sum(bytes)) as size,
            sum(bytes) as sizebytes,
            min(min_date) as min_date,
            max(max_date) as max_date
            FROM system.parts 
            WHERE active AND database=\''.$this->settings()->getDatabase().'\'
            GROUP BY table,database
        ');

        if ($flatList) {
            return $z->rows();
        }

        return $z->rowsAsTree('table');


    }

    public function isExists($database,$table)
    {
        return $this->select('
            SELECT *
            FROM system.tables 
            WHERE name=\''.$table.'\' AND database=\''.$database.'\''
        )->rowsAsTree('name');
    }
    /**
     * @param $table
     * @param int $limit
     * @return array
     */
    public function partitions($table, $limit = -1)
    {
        return $this->select('
            SELECT *
            FROM system.parts 
            WHERE like(table,\'%' . $table . '%\') AND database=\''.$this->settings()->getDatabase().'\' 
            ORDER BY max_date ' . ($limit > 0 ? ' LIMIT ' . intval($limit) : '')
        )->rowsAsTree('name');
    }

    /**
     * @param $dataBaseTableName database_name.table_name
     * @param $partition_id
     * @return Statement
     */
    public function dropPartition($dataBaseTableName, $partition_id)
    {

        $partition_id=trim($partition_id,'\'');
        $this->settings()->set('replication_alter_partitions_sync',2);
        $state = $this->write('ALTER TABLE {dataBaseTableName} DROP PARTITION :partion_id', [
            'dataBaseTableName'  => $dataBaseTableName,
            'partion_id' => $partition_id
        ]);
        return $state;
    }


    public function truncateTable($tableName)
    {
        $partions=$this->partitions($tableName);
        $out=[];
        foreach ($partions as $part_key=>$part)
        {
            $part_id=$part['partition'];
            $out[$part_id]=$this->dropPartition($tableName,$part_id);
        }
        return $out;
    }

    /**
     * @param $table_name
     * @param $days_ago
     * @param int $count_partitons_per_one
     * @return array
     */
    public function dropOldPartitions($table_name, $days_ago, $count_partitons_per_one = 100)
    {
        $days_ago = strtotime(date('Y-m-d 00:00:00', strtotime('-' . $days_ago . ' day')));

        $drop = [];
        $list_patitions = $this->partitions($table_name, $count_partitons_per_one);

        foreach ($list_patitions as $partion_id => $partition) {
            if (stripos($partition['engine'], 'mergetree') === false) {
                continue;
            }

            $min_date = strtotime($partition['min_date']);
            $max_date = strtotime($partition['max_date']);

            if ($max_date < $days_ago) {
                $drop[] = $partition['partition'];
            }
        }

        $result=[];
        foreach ($drop as $partition_id) {
            $result[$partition_id]=$this->dropPartition($table_name, $partition_id);
        }

        return $result;
    }
}