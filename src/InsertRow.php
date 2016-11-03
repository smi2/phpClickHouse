<?php

namespace ClickHouseDB;

/**
 * Class InsertRow
 * @package ClickHouseDB
 */
class InsertRow
{
    /**
     * @param array $row
     * @return string
     */
    public static function quoteRow(Array $row)
    {
        return implode(',', self::quoteValue($row));
    }

    /**
     * @param array $row
     * @return array|\Closure
     */
    public static function quoteValue(Array $row)
    {
        $quote = function ($value) {
            $enclosure = "'";
            $delimiter = ',';
            
            $delimiter_esc = preg_quote($delimiter, '/');
            $enclosure_esc = preg_quote($enclosure, '/');
            
            $type = gettype($value);

            if ($type == 'integer' || $type == 'double') {
                return strval($value);
            }

            if (is_string($value)) {
                if (preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $value)) {
                    return $enclosure . str_replace($enclosure, '\\' . $enclosure, $value) . $enclosure;
                }
                
                return $enclosure . strval($value) . $enclosure;
            }
            
            if (is_array($value)) {
                // Массивы форматируются в виде списка значений через запятую в квадратных скобках.
                // Элементы массива - числа форматируются как обычно, а даты, даты-с-временем и строки - в одинарных кавычках с такими же правилами экранирования, как указано выше.
                // Массивы сериализуются в InsertRow следующим образом: сначла массив сериализуется в строку,
                // как в формате TabSeparated, а затем полученная строка выводится в InsertRow в двойных кавычках.

                $value = self::quoteValue($value);

                $result_array = implode($delimiter, $value);
                return '[' . $result_array . ']';
            }

            if (null === $value) {
                return '';
            }

            return $value;
        };

        return array_map($quote, $row);
    }
}