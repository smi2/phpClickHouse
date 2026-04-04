<?php

namespace ClickHouseDB\Quote;

class FormatLine
{
    private static $strict = [];

    /**
     * Format
     *
     */
    public static function strictQuote(string $format): StrictQuoteLine
    {
        if (empty(self::$strict[$format]))
        {
            self::$strict[$format] = new StrictQuoteLine($format);
        }
        return self::$strict[$format];
    }

    /**
     * Array in a string for a query Insert
     *
     */
    public static function Insert(array $row, bool $skipEncode = false): string
    {
        return self::strictQuote('Insert')->quoteRow($row,$skipEncode);
    }

    /**
     * Array to TSV
     *
     */
    public static function TSV(array $row): string
    {
        return self::strictQuote('TSV')->quoteRow($row);
    }

    /**
     * Array to CSV
     *
     */
    public static function CSV(array $row): string
    {
        return self::strictQuote('CSV')->quoteRow($row);
    }
}
