<?php
namespace ClickHouseDB;

class Client
{
    /**
     * @var Transport\Http
     */
    private $_transport=false;
  
    private $_connect_username=false;
    private $_connect_password=false;
    private $_connect_uri=false;
    public function __construct($connect_params)
    {
        $this->_connect_username=$connect_params['username'];
        $this->_connect_password=$connect_params['password'];
        $this->_connect_uri='http://'.$connect_params['host'].':'.$connect_params['port'].'/';
    }

    /**
     * @return Transport\Http
     */
    public function transport()
    {
        if (!$this->_transport)
        {
            $this->_transport=new \ClickHouseDB\Transport\Http(
                $this->_connect_uri,$this->_connect_username,$this->_connect_password
            );
        }
        return $this->_transport;
    }
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

    public function write($sql,$bindings=[])
    {
        return $this->transport()->write($sql,$bindings);
    }
    /**
     *
     */
    public function database($db)
    {
        $this->settings()->database($db);
    }

    /**
     * @param $sql
     * @param array $bindings
     * @return Statement
     */
    public function select($sql,$bindings = [])
    {
        return $this->transport()->select($sql,$bindings);
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
     * @param bool $query_id
     * @return Statement
     */
    public function selectAsync($sql,$bindings=[],$query_id=false)
    {
        return $this->transport()->selectAsync($sql,$bindings);
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
        return $this->select('SHOW TABLES')->rows();
    }


    /**
     * @param array $row
     * @return array
     */
    protected function quote(array $row)
    {
        $quote = function ($value) {
            if (is_string($value))
                return "'" . $value . "'";

            if (is_array($value))
                return "'" . implode("','", $value) . "'";

            if (null === $value)
                return '';

            return $value;
        };
        return array_map($quote, $row);
    }

    public function getCountPendingQueue()
    {
        return $this->transport()->getCountPendingQueue();
    }
    public function insert($table,  $values,$columns = [])
    {
        $sql = 'INSERT INTO ' . $table;

        if (0 !== count($columns)) {
            $sql .= ' (' . implode(',', $columns) . ') ';
        }

        $sql .= 'VALUES ';

        foreach ($values as $row) {
            $sql .= ' (' . implode(',', $this->quote($row)) . '), ';
        }
        $sql = trim($sql, ', ');

        return $this->transport()->write($sql);
    }
    public function insertBatchFiles($table_name, $file_names, $columns_array)
    {
        if ($this->getCountPendingQueue()>0)
        {
            throw new \Exception("Queue must be empty, before insertBatch,need executeAsync");
        }


        $result=[];

        foreach ($file_names as $fileName)
        {
            if (!is_file($fileName) || !is_readable($fileName)) {
                throw  new \Exception("Cant read file:".$fileName);
            }
            $sql='INSERT INTO '.$table_name.' ( '.implode(",",$columns_array).' ) FORMAT CSV ';
            $result[$fileName]=$this->transport()->writeAsyncCSV($sql,$fileName);
        }
        
        // exec
        $exec=$this->executeAsync();

        // fetch resutl
        foreach ($file_names as $fileName)
        {
            if ($result[$fileName]->isError())
            {
                $result[$fileName]->error();
            }
        }
        return true;

    }
}