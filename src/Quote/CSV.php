<?php

namespace ClickHouseDB;

/**
 * @deprecated Оставлен для совместимости
 */
class CSV
{
    public static function quoteRow($row)
    {
        return FormatLine::CSV($row);
    }
}
