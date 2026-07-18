<?php

declare(strict_types=1);

namespace ClickHouseDB\Quote;

/**
 * @deprecated Left for compatibility
 */
class CSV
{
    public static function quoteRow(array $row): string
    {
        return FormatLine::CSV($row);
    }
}
