<?php

namespace ClickHouseDB\Cluster;

/**
 * Class Query
 * @package ClickHouseDB
 */
class Query
{
    /**
     * @var string
     */
    private $cluster_name;

    public function __construct($cluster_name)
    {
        $this->cluster_name=$cluster_name;
    }

    /**
     * @return string
     */
    public function getClusterName()
    {
        return $this->cluster_name;
    }

    public function getError()
    {

    }
    public function isOk()
    {

    }
    public function getNodesProcessed()
    {

    }
    public function getNodesError()
    {

    }
}

class Migration extends Query
{
    public function setUpdate($sql)
    {

    }
    public function setDowngrade($sql)
    {

    }

}