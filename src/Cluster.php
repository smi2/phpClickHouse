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
     * @var
     */
    private $cluster_name;

    /**
     * Client constructor.
     * @param $cluster_name
     * @param $connect_params
     * @param array $settings
     */
    public function __construct($cluster_name,$connect_params, $settings = [])
    {
        $connect_params['connect_by_ip']=true;

        $this->_default=new Client($connect_params,$settings);
        $this->hosts_ips   = $this->_default->getHostIPs();

        try
        {
            $this->_default->ping();
        }
        catch (Exception $E)
        {
            //@TODO : first select host not work,try change
            throw new Exception('first select host not work,try change');
        }

        $this->hosts_names = $this->clusterHosts($cluster_name);


    }

    /**
     * @return array
     */
    public function getHostsIps()
    {
        return $this->hosts_ips;
    }

    /**
     * @return array
     */
    public function getHostsNames()
    {
        return $this->hosts_names;
    }


    /**
     *
     * @return array
     */
    private function clusterHosts($cluster_name)
    {
        try
        {
            return $this->active()->select('select * from system.clusters '.($cluster_name?' WHERE cluster=\''.$cluster_name.'\'':""))->rows();
        }
        catch (Exception $E)
        {
            return false;

        }
    }

    /**
     * @param int $max_time_out
     * @param bool $changeHost
     * @return array
     */
    public function findActiveHostAndCheckCluster($max_time_out = 2, $changeHost = true)
    {
        $hostsips = $this->active()->transport()->getHostIPs();
        $selectHost = false;

        if (sizeof($hostsips) > 1) {
            list($resultGoodHost, $resultBadHost) = $this->active()->transport()->checkServerReplicas($hostsips, $max_time_out);

            if (!sizeof($resultGoodHost)) {
                throw new QueryException('All host is down: ' . json_encode($resultBadHost));
            }

            // @todo : add make some

            if ($changeHost && sizeof($resultGoodHost)) {
                $selectHost = array_rand($resultGoodHost);
                $this->transport()->setHost($selectHost);
            }
        }
        else {
            return [[$this->_connect_host => 1], [], false];
        }

        return [$resultGoodHost, $resultBadHost, $selectHost];
    }

    /**
     * @return Client
     */
    public function active()
    {
        return $this->_default;
    }

    public function random()
    {

    }
}