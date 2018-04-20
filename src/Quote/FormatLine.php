<?php

namespace ClickHouseDB\Quote;

class FormatLine
{
    /**
     *
     * @var array
     */
    private static $strict=[];

    /**
     * Форматер
     *
     * @param $format
     * @return StrictQuoteLine
     */
    private static function strictQuote($format)
    {
        if (empty(self::$strict[$format]))
        {
            self::$strict[$format]=new StrictQuoteLine($format);
        }
        return self::$strict[$format];
    }

    /**
     * Массив в строку для запроса Insert
     *
     * @param array $row
     * @return string
     */
    public static function Insert(Array $row)
    {
        return self::strictQuote('Insert')->quoteRow($row);
    }

    /**
     * Массив в строку TSV
     *
     * @param array $row
     * @return string
     */
    public static function TSV(Array $row)
    {
        return self::strictQuote('TSV')->quoteRow($row);
    }

    /**
     * Массив в строку CSV
     *
     * @param array $row
     * @return string
     */
    public static function CSV(Array $row)
    {
        return self::strictQuote('CSV')->quoteRow($row);
    }
}
