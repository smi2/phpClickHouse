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
    private $_sql_up=[];
    private $_sql_down=[];
    private $_split_chars=false;

    private function autoSplit($sql)
    {
        if ($this->_split_chars)
        {
            return explode($this->_split_chars,$sql);
        }
        return $sql;
    }

    public function setAutoSplitQuerty($split_chars)
    {
        $this->_split_chars=$split_chars;
    }
    public function addSqlUpdate($sql)
    {
        $sql=$this->autoSplit($sql);

        if (is_array($sql))
        {
          foreach ($sql as $q) $this->_sql_up[]=trim($q);
        }
        else
        {
            $this->_sql_up[]=$sql;
        }

    }
    public function addSqlDowngrade($sql)
    {
        $sql=$this->autoSplit($sql);
        if (is_array($sql))
        {
            foreach ($sql as $q) $this->_sql_down[]=trim($q);
        }
        else
        {
            $this->_sql_down[]=$sql;
        }
    }

    /**
     * @return array
     */
    public function getSqlDowngrade()
    {
        return $this->_sql_down;
    }

    /**
     * @return array
     */
    public function getSqlUpdate()
    {
        return $this->_sql_up;
    }

}