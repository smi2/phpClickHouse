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
        $this->query=$this->_request->getRequestExtendedInfo('query');
        $this->sql=$this->_request->getRequestExtendedInfo('sql');
    }

    /**
     * @return \Curler\Response
     */
    public function response()
    {
        return $this->_request->response();
    }
    public function sql()
    {
        return $this->sql;
    }
    private function parseErrorClickHouse($body)
    {
        $body=trim($body);
        $mathes=[];
        // Code: 115, e.displayText() = DB::Exception: Unknown setting readonly[0], e.what() = DB::Exception
        // Code: 192, e.displayText() = DB::Exception: Unknown user x, e.what() = DB::Exception
        // Code: 60, e.displayText() = DB::Exception: Table default.ZZZZZ doesn't exist., e.what() = DB::Exception

        if (preg_match("%Code: (\d+),\se\.displayText\(\) \=\s*DB\:\:Exception\s*:\s*(.*)\,\s*e\.what.*%ius",$body,$mathes))
        {
            return ['code'=>$mathes[1],'message'=>$mathes[2]];
        }
        return false;
    }
    public function error()
    {
        if (!$this->isError()) return false;

        $body=$this->response()->body();
        $error_no=$this->response()->error_no();

        if  (!$error_no)
        {
            $parse=$this->parseErrorClickHouse($body);

            if ($parse )
            {
                throw new DatabaseException($parse['message']."\nIN:".$this->sql(),$parse['code']);
            }
            else
            {
                $message="HttpCode:".$this->response()->http_code()." ; ".$body;
            }

        }
        else
        {
            $message="Curl error:".$error_no." ".$this->response()->error();
        }

        throw new QueryException($message);
    }
    public function isError()
    {
        return ($this->response()->http_code()!==200 || $this->response()->error_no());
    }

    /**
     * @return bool
     */
    private function init()
    {
        if ($this->_init) return false;

        if (!$this->_request->isResponseExists())
        {
            throw new QueryException('Not have response');
        }
        if ($this->isError())
        {
            $this->error();
        }
        $this->_rawData=$this->response()->json();

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
            throw  new QueryException('Can`t find meta');
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
     * @return array
     * @throws \Exception
     */
    public function extremes()
    {
        $this->init();
        return $this->extremes;
    }

    /**
     * @return mixed
     */
    public function totalTimeRequest()
    {
        $this->init();
        return $this->response()->total_time();

    }

    /**
     * @return array
     * @throws \Exception
     */
    public function extremesMin()
    {
        $this->init();
        if (empty($this->extremes['min'])) return [];
        return $this->extremes['min'];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function extremesMax()
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
        $this->response()->dump();
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
    public function info_upload()
    {
        $this->init();
        return [
                'size_upload'=>$this->response()->size_upload(),
                'upload_content'=>$this->response()->upload_content_length(),
                'speed_upload'=>$this->response()->speed_upload(),
                'time_request'=>$this->totalTimeRequest()
            ];

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