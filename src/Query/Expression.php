<?php

namespace ClickHouseDB\Query;

/**
 * Query expression
 *
 * @package ClickHouseDB
 */
class Expression
{
    /**
     * @var string
     */
    protected $expression = '';

    /**
     * Expression constructor.
     *
     * @param $expression
     */
    public function __construct($expression)
    {
        $this->expression = $expression;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->expression;
    }
}