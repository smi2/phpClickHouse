<?php

class Result
{
    private $_count_rows=-1;
    private $_meta_rows=[];
    private $_sql='';
    private $_http_data;
    private $_http_code=-1;
    private $_time=-1;

    public function __construct($http_data)
    {
        $this->_http_data=$http_data;

        $this->_count_rows=@$http_data['data']['rows'];
        $this->_meta_rows=@$http_data['data']['meta'];
        $this->_sql=@$http_data['sql'];
        $this->_time=@$http_data['time'];
        $this->_http_code=@$http_data['http_code'];


    }

    public function sql()
    {
        return $this->_sql;
    }
    public function time()
    {
        return $this->_time;
    }
    public function http_code()
    {
        return $this->_http_code;
    }
    public function extremes()
    {
        if (isset($this->_http_data['extremes']['extremes']))
        {
            return $this->_http_data['data']['extremes'];

        }
        return [];
    }
    public function totals()
    {
        if (isset($this->_http_data['data']['totals']))
        {
            return $this->_http_data['data']['totals'];

        }
        return [];
    }
    public function count()
    {
        return $this->_count_rows;
    }
    public function rows()
    {

        $out=[];
        $e=$this->_http_data;
        if (empty($e['data']['meta']))
        {
            throw  new \Exception('Can`t :'.$e['sql'].':'.$e['body']."\n".json_encode($e));
        }

        foreach ($e['data']['data'] as $rows)
        {
            $r=[];
            foreach ($e['data']['meta'] as $meta) {
                $r[$meta['name']]=$rows[$meta['name']];
            }

            $out[]=$r;
        }
        return $out;

    }
}