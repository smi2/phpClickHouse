<?php
namespace ClickHouseDB;

use Curler\CurlerRolling;
use Curler\Request;
use Curler\Response;

class Cluster
{

    /**
     * @var Client
     */
    private $_default;
    /**
     * @var array
     */
    private $_hosts_ips=[];
    /**
     * @var array
     */
    private $_hosts_names=[];

    /**
     * @var array
     */
    private $_hosts_good=[];
    /**
     * @var array
     */
    private $_hosts_bad=[];


    private $_host_name;

    private $_scanTimeOut=2;

    private $_scaned=false;

    /**
     * Cluster constructor.
     * @param $connect_params
     * @param array $settings
     * @param int $scanTimeOut
     */
    public function __construct($connect_params, $settings = [])
    {
        $this->_default=new Client($connect_params,$settings);
        $this->_host_name=$this->_default->getConnectHost();

        $this->setHostsIps(gethostbynamel($this->_host_name));
    }

    public function setScanTimeOut($scanTimeOut)
    {
        $this->_scanTimeOut = $scanTimeOut;
    }

    public function setHostsIps($hosts_ips)
    {
        $this->_hosts_ips = $hosts_ips;
    }

    /**
     * @return array
     */
    public function getAllHostsIps()
    {
        return $this->_hosts_ips;
    }

    /**
     * @return array
     */
    public function getHostsBad()
    {
        return $this->_hosts_bad;
    }

    /**
     * @return array
     */
    public function getClustersTable()
    {
        return $this->clusters;
    }


    /**
     * @return $this
     */
    public function connect()
    {
        if (!$this->_scaned)
        {
            $this->rescan();
        }
        return $this;
    }
    public function rescan()
    {
       /*
        * 1) Получаем список IP
        * 2) К каждому подключаемся по IP, через activeClient подменяя host на ip
        * 3) Достаем информацию system.clusters + system.replicas c каждой машины , overwrite { DnsCache + timeOuts }
        * 4) Определяем нужные машины для кластера/реплики
        * 5) .... ?
        */


//        $this->activeClient()->verbose();
        $statementsReplicas=[];
        $statementsClusters=[];
        foreach ($this->_hosts_ips as $ip)
        {
            $this->activeClient()->setHost($ip);

            $statementsReplicas[$ip] = $this->activeClient()->selectAsync('SELECT * FROM system.replicas');
            $statementsClusters[$ip] = $this->activeClient()->selectAsync('SELECT * FROM system.clusters');
            //
            $statementsReplicas[$ip]->getRequest()->setDnsCache(0)->timeOutMs(1000*$this->_scanTimeOut)->connectTimeOut($this->_scanTimeOut);
            $statementsClusters[$ip]->getRequest()->setDnsCache(0)->timeOutMs(1000*$this->_scanTimeOut)->connectTimeOut($this->_scanTimeOut);

        }
        $this->activeClient()->executeAsync();

        $result=[];
        $badIps=[];
        foreach ($this->_hosts_ips as $ip)
        {
            try
            {
                $result[$ip]['replicas'] = $statementsReplicas[$ip]->rows();
            }
            catch (\Exception $E)
            {
                $result[$ip]['replicas'] = false;
                $badIps[$ip]=$E->getMessage();

            }

            try
            {
                $result[$ip]['clusters'] = $statementsClusters[$ip]->rows();
            }
            catch (\Exception $E)
            {
                $result[$ip]['clusters'] = false;
                $badIps[$ip]=$E->getMessage();

            }
        }
        // Востановим DNS имя хоста в клиенте
        $this->activeClient()->setHost($this->_host_name);

        // $badIps = array(6) {  '222.222.222.44' =>  string(13) "HttpCode:0 ; " , '222.222.222.11' =>  string(13) "HttpCode:0 ; "
        $this->_hosts_bad=$badIps;

        // @todo : use total_replicas + active_replicas - for check state ?
        // total_replicas + active_replicas
        // if (!isset($row['total_replicas']))  $flag_bad=true;
        // if (!isset($row['active_replicas']))  $flag_bad=true;
        // if ($row['total_replicas']!==$row['active_replicas'])  $flag_bad=true;

//        if (in_array($this->_default->getConnectUseHost(),array_keys($this->_hosts_bad)))
//        {
//            // need change default host
//            $this->_default->setHost(array_keys($this->_hosts_good));
//        }
//
//        // request system.cluster table
//        try
//        {
//            $this->clusters = $this->activeClient()->select('select * from system.clusters')->rows();
//        }
//        catch (QueryException $E)
//        {
//            throw new TransportException('random select host not work from list marked is good');
//        }



        $this->_scaned=true;
    }



    /**
     * @return Client
     */
    public function activeClient()
    {
        return $this->_default;
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param bool $exception
     * @return Statement
     */
    public function writeCluster($cluster,$sql, $bindings = [], $exception = true)
    {
        return $this->transport()->write($sql, $bindings, $exception);
    }


}