<?php

namespace ClickHouseDB\Query\Degeneration;

/**
 * Class QueryDegeneration
 * @package ClickHouseDB
 */

class Bindings implements \ClickHouseDB\Query\Degeneration
{
    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @param array $bindings
     */
    public function bindParams(array $bindings)
    {
        foreach ($bindings as $column => $value) {
            $this->bindParam($column, $value);
        }
    }

    /**
     * @param string $column
     * @param mixed $value
     */
    public function bindParam($column, $value)
    {
        $this->bindings[$column] = $value;
    }

    /**
     * @param $sql
     * @return mixed
     */
    public function process($sql)
    {
        foreach ($this->bindings as $key => $value) {
            $valueSet = null;
            $valueSetText = null;

            if (null === $value || $value === false) {
                $valueSetText = "";
            }

            if (is_array($value)) {
                $valueSetText = "'" . implode("','", $value) . "'";
                $valueSet = implode(", ", $value);
            }

            if (is_numeric($value)) {
                $valueSetText = $value;
                $valueSet = $value;
            }

            if (is_string($value)) {
                $valueSet = $value;
                $valueSetText = "'" . $value . "'";
            }

            if ($valueSetText !== null) {
                $sql = str_ireplace(':' . $key, $valueSetText, $sql);
            }

            if ($valueSet !== null) {
                $sql = str_ireplace('{' . $key . '}', $valueSet, $sql);
            }
        }

        return $sql;
    }

}