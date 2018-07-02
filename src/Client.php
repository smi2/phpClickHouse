<?php

namespace ClickHouseDB;

use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Query\Degeneration\Bindings;
use ClickHouseDB\Query\WhereInFile;
use ClickHouseDB\Query\WriteToFile;
use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Transport\Http;
use ClickHouseDB\Transport\Stream;

class Client
{
    /**
     * @var Http
     */
    private $_transport = null;

    /**
     * @var string
     */
    private $_connect_username = '';

    /**
     * @var string
     */
    private $_connect_password = '';

    /**
     * @var string
     */
    private $_connect_host = '';

    /**
     * @var string
     */
    private $_connect_port = '';

    /**
     * @var bool
     */
    private $_connect_user_readonly = false;
    /**
     * @var array
     */
    private $_support_format = ['TabSeparated', 'TabSeparatedWithNames', 'CSV', 'CSVWithNames', 'JSONEachRow'];

    /**
     * Client constructor.
     * @param array $connect_params
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


        $this->_transport->addQueryDegeneration(new Bindings());

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

        $this->enableHttpCompression();


    }

    /**
     * if the user has only read in the config file
     *
     * @param bool $flag
     */
    public function setReadOnlyUser($flag)
    {
        $this->_connect_user_readonly = $flag;
        $this->settings()->setReadOnlyUser($this->_connect_user_readonly);
    }
    /**
     * Clear Degeneration processing request [template ]
     *
     * @return bool
     */
    public function cleanQueryDegeneration()
    {
        return $this->_transport->cleanQueryDegeneration();
    }

    /**
     * add Degeneration processing
     *
     * @param Query\Degeneration $degeneration
     * @return bool
     */
    public function addQueryDegeneration(Query\Degeneration $degeneration)
    {
        return $this->_transport->addQueryDegeneration($degeneration);
    }

    /**
     * add Conditions in query
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
     * @param string|array $host
     */
    public function setHost($host)
    {

        if (is_array($host))
        {
            $host = array_rand(array_flip($host));
        }

        $this->_connect_host = $host;
        $this->transport()->setHost($host);
    }

    /**
     * Таймаут
     *
     * @param int $timeout
     * @return Settings
     */
    public function setTimeout($timeout)
    {
        return $this->settings()->max_execution_time($timeout);
    }

    /**
     * Timeout
     *
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->settings()->getTimeOut();
    }

    /**
     * ConnectTimeOut in seconds ( support 1.5 = 1500ms )
     *
     * @param int $connectTimeOut
     */
    public function setConnectTimeOut($connectTimeOut)
    {
        $this->transport()->setConnectTimeOut($connectTimeOut);
    }

    /**
     * get ConnectTimeOut
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
    public function useSession($useSessionId = false)
    {
        if (!$this->settings()->getSessionId())
        {
            if (!$useSessionId)
            {
                $this->settings()->makeSessionId();
            } else
            {
                $this->settings()->session_id($useSessionId);
            }

        }
        return $this;
    }
    /**
     * @return mixed
     */
    public function getSession()
    {
        return $this->settings()->getSessionId();
    }

    /**
     * Query CREATE/DROP
     *
     * @param string $sql
     * @param array $bindings
     * @param bool $exception
     * @return Statement
     * @throws Exception\TransportException
     */
    public function write($sql, $bindings = [], $exception = true)
    {
        return $this->transport()->write($sql, $bindings, $exception);
    }

    /**
     * set db name
     * @param string $db
     * @return $this
     */
    public function database($db)
    {
        $this->settings()->database($db);
        return $this;
    }

    /**
     * Write to system.query_log
     *
     * @param bool $flag
     * @return $this
     */
    public function enableLogQueries($flag = true)
    {
        $this->settings()->set('log_queries', intval($flag));
        return $this;
    }

    /**
     * Compress the result if the HTTP client said that it understands data compressed with gzip or deflate
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
     * Enable / Disable HTTPS
     *
     * @param bool $flag
     * @return $this
     */
    public function https($flag = true)
    {
        $this->settings()->https($flag);
        return $this;
    }

    /**
     * Read extremes of the result columns. They can be output in JSON-formats.
     *
     * @param bool $flag
     * @return $this
     */
    public function enableExtremes($flag = true)
    {
        $this->settings()->set('extremes', intval($flag));
        return $this;
    }

    /**
     * SELECT
     *
     * @param string $sql
     * @param array $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return Statement
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function select($sql, $bindings = [], $whereInFile = null, $writeToFile = null)
    {
        return $this->transport()->select($sql, $bindings, $whereInFile, $writeToFile);
    }

    /**
     * execute run
     *
     * @return bool
     * @throws Exception\TransportException
     */
    public function executeAsync()
    {
        return $this->transport()->executeAsync();
    }

    /**
     * set progressFunction
     *
     * @param callable $callback
     */
    public function progressFunction($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Not is_callable progressFunction');
        }

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
     * prepare select
     *
     * @param string $sql
     * @param array $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return Statement
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function selectAsync($sql, $bindings = [], $whereInFile = null, $writeToFile = null)
    {
        return $this->transport()->selectAsync($sql, $bindings, $whereInFile, $writeToFile);
    }

    /**
     * SHOW PROCESSLIST
     *
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function showProcesslist()
    {
        return $this->select('SHOW PROCESSLIST')->rows();
    }

    /**
     * show databases
     *
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function showDatabases()
    {
        return $this->select('show databases')->rows();
    }

    /**
     * statement = SHOW CREATE TABLE
     *
     * @param string $table
     * @return mixed
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function showCreateTable($table)
    {
        return ($this->select('SHOW CREATE TABLE ' . $table)->fetchOne('statement'));
    }

    /**
     * SHOW TABLES
     *
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function showTables()
    {
        return $this->select('SHOW TABLES')->rowsAsTree('name');
    }

    /**
     * Get the number of simultaneous/Pending requests
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
     * @param string $table
     * @param array $values
     * @param array $columns
     * @return Statement
     * @throws Exception\TransportException
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
      * Prepares the values to insert from the associative array.
      * There may be one or more lines inserted, but then the keys inside the array list must match (including in the sequence)
      *
      * @param array $values - array column_name => value (if we insert one row) or array list column_name => value if we insert many lines
      * @return array - list of arrays - 0 => fields, 1 => list of value arrays for insertion
      */
    public function prepareInsertAssocBulk(array $values)
    {
        if (isset($values[0]) && is_array($values[0])) { //случай, когда много строк вставляется
            $preparedFields = array_keys($values[0]);
            $preparedValues = [];
            foreach ($values as $idx => $row) {
                $_fields = array_keys($row);
                if ($_fields !== $preparedFields) {
                    throw new QueryException("Fields not match: " . implode(',', $_fields) . " and " . implode(',', $preparedFields) . " on element $idx");
                }
                $preparedValues[] = array_values($row);
            }
        } else { //одна строка
            $preparedFields = array_keys($values);
            $preparedValues = [array_values($values)];
        }
        return [$preparedFields, $preparedValues];
    }

    /**
     * Inserts one or more rows from an associative array.
     * If there is a discrepancy between the keys of the value arrays (or their order) - throws an exception.
     *
     * @param string $table - table name
     * @param array $values - array column_name => value (if we insert one row) or array list column_name => value if we insert many lines
     * @return Statement
     * @throws QueryException
     * @throws Exception\TransportException
     */
    public function insertAssocBulk($table, array $values)
    {
        list($columns, $vals) = $this->prepareInsertAssocBulk($values);
        return $this->insert($table, $vals, $columns);
    }

    /**
     * insert TabSeparated files
     *
     * @param string $table_name
     * @param string|array $file_names
     * @param array $columns_array
     * @return mixed
     * @throws Exception\TransportException
     */
    public function insertBatchTSVFiles($table_name, $file_names, $columns_array=[])
    {
        return $this->insertBatchFiles($table_name, $file_names, $columns_array, 'TabSeparated');
    }

    /**
     * insert Batch Files
     *
     * @param string $table_name
     * @param string|array $file_names
     * @param array $columns_array
     * @param string $format ['TabSeparated','TabSeparatedWithNames','CSV','CSVWithNames']
     * @return array
     * @throws Exception\TransportException
     */
    public function insertBatchFiles($table_name, $file_names, $columns_array=[], $format = "CSV")
    {
        if (is_string($file_names))
        {
            $file_names = [$file_names];
        }
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        if (!in_array($format, $this->_support_format))
        {
            throw new QueryException('Format not support in insertBatchFiles');
        }

        $result = [];

        foreach ($file_names as $fileName) {
            if (!is_file($fileName) || !is_readable($fileName)) {
                throw  new QueryException('Cant read file: ' . $fileName . ' ' . (is_file($fileName) ? '' : ' is not file'));
            }

            if (empty($columns_array))
            {
                $sql = 'INSERT INTO ' . $table_name . ' FORMAT ' . $format;

            } else
            {
                $sql = 'INSERT INTO ' . $table_name . ' ( ' . implode(',', $columns_array) . ' ) FORMAT ' . $format;

            }
            $result[$fileName] = $this->transport()->writeAsyncCSV($sql, $fileName);
        }

        // exec
        $this->executeAsync();

        // fetch resutl
        foreach ($file_names as $fileName) {
            if ($result[$fileName]->isError()) {
                $result[$fileName]->error();
            }
        }

        return $result;
    }

    /**
     * insert Batch Stream
     *
     * @param string $table_name
     * @param array $columns_array
     * @param string $format ['TabSeparated','TabSeparatedWithNames','CSV','CSVWithNames']
     * @return Transport\CurlerRequest
     */
    public function insertBatchStream($table_name, $columns_array=[], $format = "CSV")
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        if (!in_array($format, $this->_support_format))
        {
            throw new QueryException('Format not support in insertBatchFiles');
        }

        if (empty($columns_array))
        {
            $sql = 'INSERT INTO ' . $table_name . ' FORMAT ' . $format;

        } else
        {
            $sql = 'INSERT INTO ' . $table_name . ' ( ' . implode(',', $columns_array) . ' ) FORMAT ' . $format;

        }

        return $this->transport()->writeStreamData($sql);
    }


    /**
     * stream Write
     *
     * @param Stream $stream
     * @param string $sql
     * @param array $bind
     * @return Statement
     * @throws Exception\TransportException
     */
    public function streamWrite(Stream $stream,$sql,$bind=[])
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before streamWrite');
        }
        return $this->transport()->streamWrite($stream,$sql,$bind);
    }


    /**
     * stream Read
     *
     * @param Stream $streamRead
     * @param string $sql
     * @param array $bind
     * @return Statement
     * @throws Exception\TransportException
     */
    public function streamRead(Stream $streamRead,$sql,$bind=[])
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before streamWrite');
        }
        return $this->transport()->streamRead($streamRead,$sql,$bind);
    }

    /**
     * Size of database
     *
     * @return mixed|null
     * @throws Exception\TransportException
     * @throws \Exception
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
     * Size of tables
     *
     * @param string $tableName
     * @return mixed
     * @throws Exception\TransportException
     * @throws \Exception
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
     * ping & check
     *
     * @return bool
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function ping()
    {
        $result = $this->select('SELECT 1 as ping')->fetchOne('ping');
        return ($result == 1);
    }

    /**
     * Tables sizes
     *
     * @param bool $flatList
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function tablesSize($flatList = false)
    {
        $z = $this->select('
        SELECT name as table,database,
            max(sizebytes) as sizebytes,
            max(size) as size,
            min(min_date) as min_date,
            max(max_date) as max_date
            FROM system.tables
            ANY LEFT JOIN 
            (
            SELECT table,database,
                        formatReadableSize(sum(bytes)) as size,
                        sum(bytes) as sizebytes,
                        min(min_date) as min_date,
                        max(max_date) as max_date
                        FROM system.parts 
                        WHERE active AND database=:database
                        GROUP BY table,database
            ) USING ( table,database )
            WHERE database=:database
            GROUP BY table,database
        ', ['database'=>$this->settings()->getDatabase()]);

        if ($flatList) {
            return $z->rows();
        }


        return $z->rowsAsTree('table');


    }


    /**
     * isExists
     *
     * @param string $database
     * @param string $table
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function isExists($database, $table)
    {
        return $this->select('
            SELECT *
            FROM system.tables 
            WHERE name=\''.$table . '\' AND database=\'' . $database . '\''
        )->rowsAsTree('name');
    }


    /**
     * List of partitions
     *
     * @param string $table
     * @param int $limit
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function partitions($table, $limit = -1)
    {
        return $this->select('
            SELECT *
            FROM system.parts 
            WHERE like(table,\'%' . $table . '%\') AND database=\'' . $this->settings()->getDatabase() . '\' 
            ORDER BY max_date ' . ($limit > 0 ? ' LIMIT ' . intval($limit) : '')
        )->rowsAsTree('name');
    }

    /**
     * dropPartition
     * @deprecated
     * @param string $dataBaseTableName database_name.table_name
     * @param string $partition_id
     * @return Statement
     * @throws Exception\TransportException
     */
    public function dropPartition($dataBaseTableName, $partition_id)
    {

        $partition_id = trim($partition_id, '\'');
        $this->settings()->set('replication_alter_partitions_sync', 2);
        $state = $this->write('ALTER TABLE {dataBaseTableName} DROP PARTITION :partion_id', [
            'dataBaseTableName'  => $dataBaseTableName,
            'partion_id' => $partition_id
        ]);
        return $state;
    }

    /**
     * Truncate ( drop all partitions )
     * @deprecated
     * @param string $tableName
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function truncateTable($tableName)
    {
        $partions = $this->partitions($tableName);
        $out = [];
        foreach ($partions as $part_key=>$part)
        {
            $part_id = $part['partition'];
            $out[$part_id] = $this->dropPartition($tableName, $part_id);
        }
        return $out;
    }

    /**
     * Returns the server's uptime in seconds.
     *
     * @return array
     * @throws Exception\TransportException
     */
    public function getServerUptime()
    {
        return $this->select('SELECT uptime() as uptime')->fetchOne('uptime');
    }


    /**
     * Read system.settings table
     *
     * @param string $like
     * @return array
     * @throws Exception\TransportException
     */
    public function getServerSystemSettings($like='')
    {
        $l=[];
        $list=$this->select('SELECT * FROM system.settings'.($like ? ' WHERE name LIKE :like':'' ),['like'=>'%'.$like.'%'])->rows();
        foreach ($list as $row) {
            if (isset($row['name'])) {$n=$row['name']; unset($row['name']) ; $l[$n]=$row;}
        }
        return $l;
    }



    /**
     * dropOldPartitions by day_ago
     * @deprecated
     *
     * @param string $table_name
     * @param int $days_ago
     * @param int $count_partitons_per_one
     * @return array
     * @throws Exception\TransportException
     * @throws \Exception
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

            // $min_date = strtotime($partition['min_date']);
            $max_date = strtotime($partition['max_date']);

            if ($max_date < $days_ago) {
                $drop[] = $partition['partition'];
            }
        }

        $result = [];
        foreach ($drop as $partition_id) {
            $result[$partition_id] = $this->dropPartition($table_name, $partition_id);
        }

        return $result;
    }
}
