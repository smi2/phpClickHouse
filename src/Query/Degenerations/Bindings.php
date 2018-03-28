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
        $this->bindings=[];
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
     * @param $sql
     * @return mixed
     */
    public function process($sql)
    {
        // CodeIgniter->bind() : https://github.com/bcit-ci/CodeIgniter/blob/develop/system/database/DB_driver.php#L920

        arsort($this->bindings);


        foreach ($this->bindings as $key => $value) {


            $valueSet = null;
            $valueSetText = null;

            if (null === $value || $value === false) {
                $valueSetText = "";
            }

            if (is_array($value)) {
                $escapedValue = $this->escapeArray($value);
                $valueSetText = "'" . implode("','", $escapedValue) . "'";
                $valueSet = implode(", ", $escapedValue);
            }

            if (is_numeric($value)) {
                $valueSetText = $value;
                $valueSet = $value;
            }

            if (is_string($value)) {
                $valueSet = $value;
                $valueSetText = "'" . $this->escapeString($value) . "'";
            }

            if ($valueSetText !== null) {
                $sql = str_ireplace(':' . $key, $valueSetText, $sql);
            }

            if ($valueSet !== null) {
                $sql = str_ireplace('{' . $key . '}', $valueSet, $sql);
            }
        }

        return ($sql);
    }

}
