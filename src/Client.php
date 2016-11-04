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
     * @var
     */
    private $_connect_username = false;

    /**
     * @var
     */
    private $_connect_password = false;

    /**
     * @var
     */
    private $_connect_host = false;

    /**
     * @var
     */
    private $_connect_port = false;

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

    }

    /**
     * @return bool
     */
    public function cleanQueryDegeneration()
    {
        return $this->_transport->cleanQueryDegeneration();
    }

    /**
     * @param Query\Degeneration $degeneration
     * @return bool
     */
    public function addQueryDegeneration(Query\Degeneration $degeneration)
    {
        return $this->_transport->addQueryDegeneration($degeneration);
    }

    /**
     * @return bool
     */
    public function enableQueryConditions()
    {
        return $this->_transport->addQueryDegeneration(new \ClickHouseDB\Query\Degeneration\Conditions());
    }
    /**
     * Set connection host
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
     * @param $timeout
     * @return Settings
     */
    public function setTimeout($timeout)
    {
       return $this->settings()->max_execution_time($timeout);
    }

    /**
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
     * @return bool
     */
    public function getConnectHost()
    {
        return $this->_connect_host;
    }

    /**
     * @return bool
     */
    public function getConnectPassword()
    {
        return $this->_connect_password;
    }

    /**
     * @return bool
     */
    public function getConnectPort()
    {
        return $this->_connect_port;
    }

    /**
     * @return bool
     */
    public function getConnectUsername()
    {
        return $this->_connect_username;
    }

    /**
     * @return Http
     */
    public function getTransport()
    {
        return $this->_transport;
    }


    /**
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
     */
    public function database($db)
    {
        $this->settings()->database($db);
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
     * @return bool
     */
    public function executeAsync()
    {
        return $this->transport()->executeAsync();
    }

    /**
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
     * @return array
     */
    public function showProcesslist()
    {
        return $this->select('SHOW PROCESSLIST')->rows();
    }

    /**
     * @return array
     */
    public function showDatabases()
    {
        return $this->select('show databases')->rows();
    }

    public function showCreateTable($table)
    {
        return ($this->select('SHOW CREATE TABLE '.$table)->fetchOne('statement'));
    }

    /**
     * @return array
     */
    public function showTables()
    {
        return $this->select('SHOW TABLES')->rowsAsTree('name');
    }

    /**
     * @return int
     */
    public function getCountPendingQueue()
    {
        return $this->transport()->getCountPendingQueue();
    }

    /**
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
     * Async insert TabSeparated files
     *
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
     * @param $table_name
     * @param $file_names
     * @param $columns_array
     * @param $format string ['TabSeparated','TabSeparatedWithNames','CSV','CSVWithNames']
     * @return array
     */
    public function insertBatchFiles($table_name, $file_names, $columns_array,$format="CSV")
    {
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
                throw  new QueryException('Cant read file: ' . $fileName);
            }

            $sql = 'INSERT INTO ' . $table_name . ' ( ' . implode(',', $columns_array) . ' ) FORMAT '.$format;
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
    public function tablesSize()
    {
        return $this->select('
            SELECT table,
            formatReadableSize(sum(bytes)) as size,
            sum(bytes) as sizebytes,
            min(min_date) as min_date,
            max(max_date) as max_date
            FROM system.parts
            WHERE active
            GROUP BY table
        ')->rowsAsTree('table');
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
            WHERE like(table,\'%' . $table . '%\')  
            ORDER BY max_date ' . ($limit > 0 ? ' LIMIT ' . intval($limit) : '')
        )->rowsAsTree('name');
    }

    /**
     * @param $tableName
     * @param $partition_id
     */
    public function dropPartition($tableName, $partition_id)
    {
        $state = $this->write('ALTER TABLE {tableName} DROP PARTITION :partion_id', [
            'tableName'  => $tableName,
            'partion_id' => $partition_id
        ]);
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

        foreach ($drop as $partition_id) {
            $this->dropPartition($table_name, $partition_id);
        }

        return $drop;
    }


}