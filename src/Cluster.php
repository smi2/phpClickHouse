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
        $connect_params['connect_by_ip']=true;

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
            $this->clusters = $this->active()->select('select * from system.clusters')->rows();
        }
        catch (QueryException $E)
        {
            throw new TransportException('random select host not work from list marked is good');
        }

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