<?php

class DB
{
    private $_profile=false;
    private $_readonly=1;
    private $_user=false;
    private $extremes=1;
    private $url=false;
    private $use_db=false;
    private $_password=false;
    private $_max_rows_to_read=1000000000;
    private $_max_execution_time=20;
    public function __construct($params)
    {
        $this->url="http://{$params['host']}:{$params['port']}/";
        $this->_user=$params['user'];
        $this->_password=$params['password'];
    }
    public function max_execution_time($time)
    {
        $this->_max_execution_time=$time;
    }
    public function readonly($readonly)
    {
        $this->_readonly=$readonly;
    }
    public function profile($profile)
    {
        $this->_profile=$profile;
    }

    /**
     * @param $db
     * @return $this
     */
    public function database($db)
    {
        $this->use_db=$db;
        return $this;
    }
    private function exec($query,$writeQuery=false,$insert_file=false)
    {
        $q=[];
        $q['user']=$this->_user;
        $q['password']=$this->_password;


        if ($this->use_db)
        {
            $q['database']=$this->use_db;
        }
        $q['extremes']=$this->extremes;
        $q['max_rows_to_read']=$this->_max_rows_to_read;
        $q['max_execution_time']=$this->_max_execution_time;
        $q['max_execution_time']=$this->_max_execution_time;



        if (!$writeQuery)
        {
            $q['readonly']='1';

            if (stripos($query,'FORMAT ')===false)
            {
                $query=$query.' '.'FORMAT JSON';
            }

        }
        else
        {

        }

        if ($insert_file)
        {
            $q['readonly']='0';
            $q['query']=$query;
            $query=file_get_contents($insert_file);
        }
//
        $url=$this->url.'?'.http_build_query($q);
        $result=NetworkCurl::getPage($url,$query);

        if ($writeQuery)
        {
            if ($result['http_code']!=200)
            {
                throw new \Exception($result['body']);
            }
        }


        $result['sql']=$query;
        $result['data']=[];
        $result['total_time']=$result['info']['total_time'];
        if ($result['http_code']==200)
        {
            $result['data']=json_decode($result['body'],true);
            if (is_array($result['data']))
            {
                unset($result['body']);

            }
        }
        else
        {
            $result['data']='error:';
        }
        unset($result['info']);

        return $result;
    }
    public function extremes($flag)
    {
        $this->extremes=$flag;
    }
    public function db()
    {
    }
    public function query($sql)
    {
        return $this->exec($sql);
    }

    public function insert_tab_file($fileName,$table_name,$columns_array)
    {
// из файла читаем первую строку достаем названия колонок и вставляем

        if (!is_file($fileName) || !is_readable($fileName)) {
            throw  new \Exception("Cant read file:".$fileName);
        }



        $sql='INSERT INTO '.$table_name.' ( '.implode(",",$columns_array).' ) FORMAT CSV ';


        $result=$this->exec($sql,true,$fileName);
        if ($result['http_code']!=200)
        {
            throw  new \Exception('Error insert'.json_encode($result));
        }
        return true;

    }
    public function write($sql)
    {
        return $this->exec($sql,true);
    }

    /**
     * @param $sql
     * @param int $dump
     * @return Result
     * @throws \Exception
     */
    public function select($sql)
    {
        return new \ClickHouseDB\Result($this->query($sql));
    }
    public function show_processlist()
    {
        return $this->select('SHOW PROCESSLIST')->rows();
    }

    public function show_databases()
    {
        return $this->select('show databases')->rows();
    }
    public function show_tables()
    {
        return $this->select('SHOW TABLES')->rows();
    }
}