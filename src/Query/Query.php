<?php

namespace ClickHouseDB;

/**
 * Class Query
 * @package ClickHouseDB
 */
class Query
{
    /**
     * @var string
     */
    protected $sql;

    /**
     * @var null
     */
    protected $format = null;

    /**
     * @var array
     */
    private $degenerations=[];

    /**
     * Query constructor.
     * @param $sql
     * @param array $bindings
     */
    public function __construct($sql,$degenerations=[])
    {
        if (!trim($sql))
        {
            throw new QueryException('Empty Query');
        }
        $this->sql = $sql;
        $this->degenerations=$degenerations;
    }

    /**
     * @param $format
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * @return string
     */
    public function toSql()
    {
        if (null !== $this->format) {
            $this->sql = $this->sql . ' FORMAT ' . $this->format;
        }

        if (sizeof($this->degenerations))
        {
            foreach ($this->degenerations as $degeneration)
            {
                if ($degeneration instanceof \ClickHouseDB\Query\Degeneration)
                $this->sql=$degeneration->process($this->sql);
            }
        }

        return $this->sql;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toSql();
    }
}