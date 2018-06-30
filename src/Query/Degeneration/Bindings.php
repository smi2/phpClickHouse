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
     * Can overwrite use CodeIgniter->escape_str()  https://github.com/bcit-ci/CodeIgniter/blob/develop/system/database/DB_driver.php#L920
     *
     * @param string $value
     * @return string
     */
    private function escapeString($value)
    {
//        $non_displayables = array(
//            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
//            '/%1[0-9a-f]/',             // url encoded 16-31
//            '/[\x00-\x08]/',            // 00-08
//            '/\x0b/',                   // 11
//            '/\x0c/',                   // 12
//            '/[\x0e-\x1f]/'             // 14-31
//        );
//        foreach ( $non_displayables as $regex ) $data = preg_replace( $regex, '', $data );
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
     * Compile Bindings
     *
     * @param string $sql
     * @return mixed
     */
    public function process($sql)
    {
        // Can try use
        // CodeIgniter->bind()
        // https://github.com/bcit-ci/CodeIgniter/blob/develop/system/database/DB_driver.php#L920

        arsort($this->bindings);

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
                $sql = str_ireplace(':' . $key, $formattedParameter, $sql);
            }

            if ($valueSet !== null) {
                $sql = str_ireplace('{' . $key . '}', $valueSet, $sql);
            }
        }

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
