<?php

declare(strict_types=1);

namespace ClickHouseDB\Query\Degeneration;

use ClickHouseDB\Exception\UnsupportedParameterType;
use ClickHouseDB\Query\Degeneration;
use DateTimeInterface;
use function array_map;
use function implode;
use function is_array;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;

class Bindings implements Degeneration
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
        $this->bindings = [];
        foreach ($bindings as $column => $value) {
            $this->bindParam($column, $value);
        }
    }

    /**
     * @param string $column
     * @param mixed  $value
     */
    public function bindParam($column, $value)
    {
        $this->bindings[$column] = $value;
    }

    /**
     * Escape an string
     *
     * @param string $value
     * @return string
     */
    private function escapeString($value)
    {
        return addslashes($value);
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to [[bindValue()]] except that it binds multiple values at a time.
     *
     * @param string $sql
     * @param array $binds
     * @param string $pattern
     * @return string
     */
    public function compile_binds($sql, $binds,$pattern)
    {
        return preg_replace_callback($pattern, function($m) use ($binds){
            if(isset($binds[$m[1]])){ // If it exists in our array
                return $binds[$m[1]]; // Then replace it from our array
            }

            return $m[0]; // Otherwise return the whole match (basically we won't change it)
        }, $sql);
    }

    /**
     * Compile Bindings
     *
     * @param string $sql
     * @return mixed
     */
    public function process($sql)
    {
        $bindFormatted=[];
        $bindRaw=[];
        foreach ($this->bindings as $key => $value) {
            if (is_array($value)) {
                $valueSet = implode(', ', $value);

                $values = array_map(
                    function ($value) {
                        return $this->formatParameter($value);
                    },
                    $value
                );

                $formattedParameter = implode(',', $values);
            } else {
                $valueSet           = $value;
                $formattedParameter = $this->formatParameter($value);
            }

            if ($formattedParameter !== null) {
                $bindFormatted[$key]=$formattedParameter;
            }

            if ($valueSet !== null) {
                $bindRaw[$key]=$valueSet;
            }
        }

        for ($loop=0;$loop<2;$loop++)
        {
            // dipping in binds
            // example ['A' => '{B}' , 'B'=>':C','C'=>123]
            $sql=$this->compile_binds($sql,$bindRaw,'#{([\w+]+)}#');
        }
        $sql=$this->compile_binds($sql,$bindFormatted,'#:([\w+]+)#');

        return $sql;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function formatParameter($value)
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if (is_float($value) || is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if (is_object($value) && is_callable([$value, '__toString'])) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            return $this->formatStringParameter($this->escapeString($value));
        }

        throw UnsupportedParameterType::new($value);
    }

    /**
     * @return string
     */
    private function formatStringParameter($value)
    {
        return sprintf("'%s'", $value);
    }
}
