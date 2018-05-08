<?php

namespace ClickHouseDB\Quote;

/**
 * @deprecated Left for compatibility
 */
class CSV
{
    public static function quoteRow($row)
    {
        return FormatLine::CSV($row);
    }
}
