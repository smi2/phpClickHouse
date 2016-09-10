<?php
namespace ClickHouseDB;

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
    private $hosts_ips=[];
    /**
     * @var array
     */
    private $hosts_names=[];

    /**
     * @var array
     */
    private $_hosts_good=[];
    /**
     * @var array
     */
    private $_hosts_bad=[];

    /**
     * Cluster constructor.
     * @param $connect_params
     * @param array $settings
     * @throws TransportException
     */
    public function __construct($connect_params, $settings = [])
    {
//        $connect_params['connect_by_ip']=true;


        /*
         * 1) Получаем список IP
         * 2) К каждому подключаемся по IP
         * 3) Достаем информацию system.clusters + system.replicas c каждой машины
         * 4) Определяем нужные машины для кластера/реплики по Name
         * 5) Список
         *
         */



        if (!empty($connect_params['connect_by_ip']))
        {
            $hosts=$this->getHostIPs();
            shuffle($hosts);
            $this->_connect_use_host = $hosts[0]; // set first random ip of hosts
            $this->_connect_by_ip    = true;
        }


        $this->_default=new Client($connect_params,$settings);
        $this->hosts_ips   = $this->_default->getHostIPs();

        $this->initActiveHostAndCheckCluster();

        if (in_array($this->_default->getConnectUseHost(),array_keys($this->_hosts_bad)))
        {
            // need change default host
            $this->_default->setHost(array_keys($this->_hosts_good));
        }

        // request system.cluster table
        try
        {
            $this->clusters = $this->activeClient()->select('select * from system.clusters')->rows();
        }
        catch (QueryException $E)
        {
            throw new TransportException('random select host not work from list marked is good');
        }

    }
    /**
     * @return array
     */
    public function getHostIPs()
    {
        return gethostbynamel($this->_connect_host);
    }
    private function debug($msg)
    {
        echo "\t".$msg."\n\n\n";
    }
    /**
     * @return array
     */
    public function getAllHostsIps()
    {
        return $this->hosts_ips;
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
     * @param $list_hosts
     * @param $time_out
     * @return array
     */
    public function checkServerReplicas($list_hosts, $time_out)
    {
        // @todo rewrite

        $query['query'] = 'SELECT * FROM system.replicas FORMAT JSON';
        $query['user'] = $this->_username;
        $query['password'] = $this->_password;

        $resultGoodHost = [];
        $resultBadHost = [];

        $statements = [];
        foreach ($list_hosts as $host) {
            $request = new Request();
            $url = 'http://' . $host . ":" . $this->_port . '?' . http_build_query($query);

            $request->url($url)
                ->GET()
                ->verbose(false)
                ->timeOut($time_out)
                ->connectTimeOut($time_out)
                ->setDnsCache(0);

            $this->_curler->addQueLoop($request);
            $statements[$host] = new \ClickHouseDB\Statement($request);
        }

        $this->_curler->execLoopWait();

        foreach ($statements as $host => $statement) {
            if ($statement->isError()) {
                $resultBadHost[$host] = 1;
            }
            else {
                $result = $statement->rows();
                $flag_bad = false;
//                foreach ($result as $row)
//                {
//                    if (!isset($row['total_replicas']))  $flag_bad=true;
//                    if (!isset($row['active_replicas']))  $flag_bad=true;
//                    if ($row['total_replicas']!==$row['active_replicas'])  $flag_bad=true;
//
//                    if ($flag_bad) break;
//                }


                if ($flag_bad) {
                    $resultBadHost[$host] = $result;
                }
                else {
                    $resultGoodHost[$host] = $result;
                }
            }
        }

        // @todo : use total_replicas + active_replicas - for check state ?
        // total_replicas + active_replicas

        return [$resultGoodHost, $resultBadHost];
    }



    /**
     * @param int $max_time_out
     * @return bool
     */
    private function initActiveHostAndCheckCluster($max_time_out = 2)
    {
        if (sizeof($this->hosts_ips) > 1) {
            list($resultGoodHost, $resultBadHost) = $this->activeClient()->transport()->checkServerReplicas($this->hosts_ips, $max_time_out);

            if (!sizeof($resultGoodHost)) {
                throw new QueryException('All host is down: ' . json_encode($resultBadHost));
            }

            $this->_hosts_good=$resultGoodHost;
            $this->_hosts_bad=$resultBadHost;
        }
        else {
            $this->_hosts_good=[$this->activeClient()->getConnectUseHost() => 1];
            $this->_hosts_bad=[];
        }
        return true;
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