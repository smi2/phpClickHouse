<?php

namespace ClickHouseDB\Query\Degeneration;

use DateTimeInterface;
use function array_map;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function str_ireplace;

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
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

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
        // return str_replace("'", "''", remove_invisible_characters($str, FALSE));
        return addslashes($value);
    }

    /**
     * Escape an array
     *
     * @param array $values
     * @return array
     */
    private function escapeArray($values)
    {
        $escapedValues = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $escapedValues[] = $value;
            } elseif (is_string($value)) {
                $escapedValues[] = $this->escapeString($value);
            } elseif (is_array($value)) {
                $escapedValues[] = $this->escapeArray($value);
            }
        }

        return $escapedValues;
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
            }else{
                return $m[0]; // Otherwise return the whole match (basically we won't change it)
            }
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
        arsort($this->bindings);

        $bindFormatted=[];
        $bindRaw=[];
        foreach ($this->bindings as $key => $value) {
            $valueSet           = null;
            $formattedParameter = null;

            if ($value === null || $value === false) {
                $formattedParameter = '';
            }

            if (is_array($value)) {
                $escapedValues = $this->escapeArray($value);

                $escapedValues = array_map(
                    function ($escapedValue) {
                        if (is_string($escapedValue)) {
                            return $this->formatStringParameter($escapedValue);
                        }

                        return $escapedValue;
                    },
                    $escapedValues
                );

                $formattedParameter = implode(',', $escapedValues);
                $valueSet           = implode(', ', $escapedValues);
            }

            if (is_float($value) || is_int($value)) {
                $formattedParameter = $value;
                $valueSet           = $value;
            }

            if (is_string($value)) {
                $valueSet           = $value;
                $formattedParameter = $this->formatStringParameter($this->escapeString($value));
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
     * @return string
     */
    private function formatStringParameter($value)
    {
        return sprintf("'%s'", $value);
    }
}
