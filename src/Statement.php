<?php
namespace ClickHouseDB;

/**
 * Class Statement
 * @package ClickHouseDB
 */
class Statement
{
    private $_rawData;
    private $_http_code=-1;
    private $_request=null;
    private $_init=false;

    /**
     * @var Query
     */
    private $query;
    /**
     * @var string
     */
    private $sql=false;
    /**
     * @var array
     */
    private $meta;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $totals;

    /**
     * @var array
     */
    private $extremes;

    /**
     * @var int
     */
    private $rows;

    /**
     * @var
     */
    private $rows_before_limit_at_least=false;

    /**
     * @var
     */
    private $rawResult;

    /**
     * @var array
     */
    private $array_data=[];

    public function __construct(\Curler\Request $request)
    {
        $this->_request=$request;
        $this->query=$this->_request->getExtendinfo('query');
        $this->sql=$this->_request->getExtendinfo('sql');

    }
    public function sql()
    {
        return $this->sql;
    }
    public function error()
    {
        // @todo normal Exception
        // @todo parse error answer
        // Code: 115, e.displayText() = DB::Exception: Unknown setting readonly[0], e.what() = DB::Exception

        $this->_request->response()->http_code();
        $body=$this->_request->response()->body();
        $this->_request->response()->dump();
        $this->_request->dump();

        throw new \Exception($body);
    }
    public function isError()
    {
        return ($this->_request->response()->http_code()!==200);
    }
    private function init()
    {
        if ($this->_init) return false;

        $this->_http_code=$this->_request->response()->http_code();
        if ($this->_http_code!==200)
        {
            $this->error();
        }
        $this->_rawData=$this->_request->response()->json();


        if (!$this->_rawData)
        {
            $this->_init=true;
            return false;
        }

        foreach (['meta','data','totals','extremes','rows','rows_before_limit_at_least'] as $key)
        {
            if (isset($this->_rawData[$key]))
            {
                $this->{$key}=$this->_rawData[$key];
            }
        }
        if (empty($this->meta))
        {
            throw  new \Exception('Can`t find meta');
        }
        $this->array_data=[];
        foreach ($this->data as $rows)
        {
            $r=[];
            foreach ($this->meta as $meta) {
                $r[$meta['name']]=$rows[$meta['name']];
            }
            $this->array_data[]=$r;
        }


        return true;
    }
    /**
     * @return mixed
     */
    public function extremes()
    {
        $this->init();
        return $this->extremes;
    }
    public function extremes_min()
    {
        $this->init();
        if (empty($this->extremes['min'])) return [];
        return $this->extremes['min'];
    }
    public function extremes_max()
    {
        $this->init();
        if (empty($this->extremes['max'])) return [];
        return $this->extremes['max'];
    }
    /**
     * @return mixed
     */
    public function totals()
    {
        $this->init();
        return $this->totals;
    }

    public function dumpRaw()
    {
        print_r($this->_rawData);
    }
    public function dump()
    {
        $this->_request->dump();
        $this->_request->response()->dump();
    }
    public function countAll()
    {
        $this->init();
        return $this->rows_before_limit_at_least;
    }
    public function count()
    {
        $this->init();
        return $this->rows;
    }
    public function fetchOne($key=false)
    {
        $this->init();
        if (isset($this->array_data[0]))
        {
            if ($key)
            {
                if (isset($this->array_data[0][$key]))
                {
                    return $this->array_data[0][$key];
                }
                else
                {
                    return null;
                }
            }
            return $this->array_data[0];
        }
        return null;
    }
    public function rowsAsTree($path)
    {
        $this->init();
        $out=[];
        foreach ($this->array_data as $row)
        {
            $d=$this->array_to_tree($row,$path);
            $out=array_replace_recursive($d,$out);
        }
        return $out;

    }
    public function rows()
    {
        $this->init();
        return $this->array_data;
    }
    private function array_to_tree($arr, $path=null)
    {
        if (is_array($path))
        {
            $keys=$path;
        }else
        {

            $args=func_get_args();
            array_shift($args);
            if (sizeof($args)<2)
            {

                $separator='.';
                $keys = explode($separator, $path);

            }
            else
            {
                $keys=$args;
            }
        }
        //
        $tree=$arr;
        while (count($keys)) {
            $key=array_pop($keys);
            if (isset($arr[$key])) {$val=$arr[$key];} else $val=$key;

            $tree=array($val => $tree);
        }
        return $tree;
    }
}