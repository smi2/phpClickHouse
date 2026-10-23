<?php

declare(strict_types=1);

namespace ClickHouseDB\Quote;

class FormatLine
{
    private static array $strict = [];

    public static function strictQuote(string $format): StrictQuoteLine
    {
        if (empty(self::$strict[$format]))
        {
            self::$strict[$format] = new StrictQuoteLine($format);
        }
        return self::$strict[$format];
    }

    /**
     * @psalm-taint-specialize
     *
     * @psalm-flow ($row) -> return
     *
     * @psalm-taint-escape ($skipEncode is false ? 'sql' : null)
     */
    public static function Insert(array $row, bool $skipEncode = false): string
    {
        return self::strictQuote('Insert')->quoteRow($row, $skipEncode);
    }

    /**
     * @psalm-taint-specialize
     *
     * @psalm-flow ($row) -> return
     */
    public static function TSV(array $row): string
    {
        return self::strictQuote('TSV')->quoteRow($row);
    }

    /**
     * @psalm-taint-specialize
     *
     * @psalm-flow ($row) -> return
     */
    public static function CSV(array $row): string
    {
        return self::strictQuote('CSV')->quoteRow($row);
    }
}
