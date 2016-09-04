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
     * @var bool
     */
    private $_connect_by_ip= false;

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
        $this->_connect_use_host    = $connect_params['host'];


        if (!empty($connect_params['connect_by_ip']))
        {
            $hosts=$this->getHostIPs();
            shuffle($hosts);
            $this->_connect_use_host = $hosts[0]; // set first random ip of hosts
            $this->_connect_by_ip    = true;
        }

        // init transport class
        $this->_transport = new Http(
            $this->_connect_use_host,
            $this->_connect_port,
            $this->_connect_username,
            $this->_connect_password
        );

        // apply settings to transport class
        $this->settings()->database('default');
        if (sizeof($settings)) {
            $this->settings()->apply($settings);
        }

    }


    /**
     * @return mixed
     */
    public function getConnectUseHost()
    {
        return $this->_connect_use_host;
    }
    /**
     * @return array
     */
    public function getHostIPs()
    {
        return gethostbynamel($this->_connect_host);
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
     * @param bool $flag
     */
    public function enableHttpCompression($flag = true)
    {
        $this->settings()->enableHttpCompression($flag);
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param null $whereInFile
     * @return Statement
     */
    public function select($sql, $bindings = [], $whereInFile = null)
    {
        return $this->transport()->select($sql, $bindings, $whereInFile);
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
     * @param null $whereInFile
     * @return Statement
     */
    public function selectAsync($sql, $bindings = [], $whereInFile = null)
    {
        return $this->transport()->selectAsync($sql, $bindings, $whereInFile);
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
            $sql .= ' (' . CSV::quoteRow($row) . '), ';
        }
        $sql = trim($sql, ', ');

        return $this->transport()->write($sql);
    }

    /**
     * @param $table_name
     * @param $file_names
     * @param $columns_array
     * @return array
     */
    public function insertBatchFiles($table_name, $file_names, $columns_array)
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        $result = [];

        foreach ($file_names as $fileName) {
            if (!is_file($fileName) || !is_readable($fileName)) {
                throw  new QueryException('Cant read file: ' . $fileName);
            }

            $sql = 'INSERT INTO ' . $table_name . ' ( ' . implode(',', $columns_array) . ' ) FORMAT CSV ';
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
     * @return array
     */
    public function tablesSize()
    {
        return $this->select('
            SELECT table,
            formatReadableSize(sum(bytes)) as size,
            min(min_date) as min_date,
            max(max_date) as max_date
            FROM system.parts
            WHERE active
            GROUP BY table
        ')->rowsAsTree('table');
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

        if ($state->isError()) {
            $state->error();
        }
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