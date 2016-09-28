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
    /**
     * @var int
     */
    private $timeout=0;

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
    /**
     * @param $seconds float
     * @return $this
     */
    public function setTimeout($seconds)
    {
        $this->timeout=$seconds;
        return $this;
    }

    /**
     * @return float
     */
    public function getTimeout()
    {
        return floatval($this->timeout);
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

    /**
     * @param $split_chars
     * @return $this
     */
    public function setAutoSplitQuery($split_chars)
    {
        $this->_split_chars=$split_chars;
        return $this;
    }

    /**
     * @param $sql
     * @return $this
     */
    public function addSqlUpdate($sql)
    {
        $sql=$this->autoSplit($sql);

        if (is_array($sql))
        {
          foreach ($sql as $q)
          {
              $q=trim($q);
              if ($q)
              $this->_sql_up[]=$q;
          }
        }
        else
        {
            $this->_sql_up[]=$sql;
        }
        return $this;
    }

    /**
     * @param $sql
     * @return $this
     */
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
        return $this;
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