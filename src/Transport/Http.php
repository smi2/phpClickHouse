<?php

namespace ClickHouseDB\Transport;

/**
 * Class Http like simpleCurl
 * @package ClickHouseDB\Transport
 */
class Http
{
    private $username = null;
    private $password = null;
    private $host='';
    private $port=0;
    private $_verbose=false;

    /**
     * @var \Curler\CurlerRolling
     */
    private $curler=false;
    /**
     * @var \ClickHouseDB\Settings
     */
    private $_settings=false;

    public function __construct($host,$port, $username, $password)
    {
        $this->setHost($host,$port);

        $this->username = $username;
        $this->password = $password;
        $this->_settings=new \ClickHouseDB\Settings($this);
        
        $this->curler=new \Curler\CurlerRolling();
        $this->curler->setSimultaneousLimit(10);
    }


    public function setHost($host,$port=-1)
    {
        if ($port>0)
        {
            $this->port=$port;
        }
        $this->host=$host;
    }
    public function getUri()
    {
        return 'http://'.$this->host.':'.$this->port;
    }

    public function checkServers($list_hosts,$time_out)
    {

        // @todo add WHERE database=XXXX


        $query['query']='SELECT * FROM system.replicas FORMAT JSON';
        $query['user']=$this->username;
        $query['password']=$this->password;

        $resultGoodHost=[];
        $resultBadHost=[];

        $statements=[];
        foreach ($list_hosts as $host)
        {
            $request=new \Curler\Request();
            $url='http://'.$host.":".$this->port.'?'.http_build_query($query);
            $request->url($url)->GET()->verbose(false)->timeOut($time_out);
            $this->curler->addQueLoop($request);
            $statements[$host]=new \ClickHouseDB\Statement($request);
        }
        $this->curler->execLoopWait();

        foreach ($statements as $host=>$statement)
        {
            if ($statement->isError())
            {
                $resultBadHost[$host]=1;
                $statement->error();
            }
            else
            {
                $resultGoodHost[$host]=$statement->rows();
            }
        }

        // @todo : use total_replicas + active_replicas - for check state ?
        // total_replicas + active_replicas
        return [$resultGoodHost,$resultBadHost];

    }
    /**
     * @return array
     */
    public function getHostIPs()
    {
        return gethostbynamel($this->host);

    }
    /**
     * @return \ClickHouseDB\Settings
     */
    public function settings()
    {
        return $this->_settings;
    }
    public function verbose($flag)
    {
        $this->_verbose=$flag;
        return $flag;
    }

    private function getUrl($params=[])
    {
        $settings=$this->settings()->getSettings();

        if (is_array($params) && sizeof($params))
        {
            $settings=array_merge($settings,$params);
        }
        return $this->getUri().'?'.http_build_query($settings);
    }

    /**
     * @param \ClickHouseDB\Query $query
     * @param $urlParams
     * @return \Curler\Request
     * @throws \Exception
     */
    private function makeRequest(\ClickHouseDB\Query $query, $urlParams=[])
    {
        $req_id=false;

        $sql=$query->toSql();
        $new=new \Curler\Request($req_id);
        $url=$this->getUrl($urlParams);

        $extendinfo=[
            'sql'=>$sql,
            'query'=>$query
        ];

        $new->url($url)->auth($this->username,$this->password)->POST()->parameters_json($sql)->extendinfo($extendinfo);
        $new->verbose($this->_verbose);
        return $new;
    }

    /**
     * @param $sql
     * @param $file_name
     * @return \ClickHouseDB\Statement
     */
    public function writeAsyncCSV($sql, $file_name)
    {
        $query = new \ClickHouseDB\Query($sql);
        $request=new \Curler\Request();
        $url=$this->getUrl(['readonly'=>0,'query'=>$query->toSql()]);

        $extendinfo=['sql'=>$sql,'query'=>$query];

        $request->url($url)->auth($this->username,$this->password)->POST()->extendinfo($extendinfo);
        $request->verbose($this->_verbose);

        $request->setCallbackFunction(
            function (\Curler\Request $request)
            {
                fclose($request->getInfileHandle());
            }
        );
        $request->setInfile($file_name);
        $this->curler->addQueLoop($request);

        return new \ClickHouseDB\Statement($request);
    }



    public function getCountPendingQueue()
    {
        return $this->curler->countPending();
    }
    /**
     * @param \ClickHouseDB\Query $query
     * @param bool $id
     * @return \Curler\Request
     */
    public function getRequestRead(\ClickHouseDB\Query $query)
    {
        $urlParams=[  'readonly'=>1,'extremes'=>1 ];
        return $this->makeRequest($query,$urlParams);

    }
    /**
     * @param \ClickHouseDB\Query $query
     * @param bool $id
     * @return \Curler\Request
     */
    public function getRequestWrite(\ClickHouseDB\Query $query)
    {
        $urlParams=['readonly'=>0];
        return $this->makeRequest($query,$urlParams);
    }

    /**
     * @param $sql
     * @param $bindings
     * @return \Curler\Request
     */
    private function prepareSelect($sql,$bindings)
    {
        $query = new \ClickHouseDB\Query($sql, $bindings);
        $query->setFormat('JSON');
        return $this->getRequestRead($query);

    }
    /**
     * @param $sql
     * @param $bindings
     * @return \Curler\Request
     */
    private function prepareWrite($sql,$bindings)
    {
        $query = new \ClickHouseDB\Query($sql, $bindings);
        return $this->getRequestWrite($query);

    }

    /**
     *
     * @return bool
     */
    public function executeAsync()
    {
        return $this->curler->execLoopWait();
    }

    /**
     * @param $sql
     * @param array $bindings
     * @return \ClickHouseDB\Statement
     */
    public function selectAsync($sql, array $bindings = [])
    {
        $request=$this->prepareSelect($sql,$bindings);
        $this->curler->addQueLoop($request);
        return new \ClickHouseDB\Statement($request);
    }
    /**
     * @param $sql
     * @param array $bindings
     * @return \ClickHouseDB\Statement
     * @throws \Exception
     */
    public function write($sql, array $bindings = [])
    {
        $request=$this->prepareWrite($sql,$bindings);
        $code=$this->curler->execOne($request);
        return new \ClickHouseDB\Statement($request);
    }

    /**
     * @param $sql
     * @param array $bindings
     * @return \ClickHouseDB\Statement
     * @throws \Exception
     */
    public function select($sql, array $bindings = [])
    {
        $request=$this->prepareSelect($sql,$bindings);
        $code=$this->curler->execOne($request);
    
        return new \ClickHouseDB\Statement($request);
    }

}