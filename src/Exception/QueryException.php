<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

use LogicException;

class QueryException extends LogicException implements ClickHouseException
{
    public static function cannotInsertEmptyValues() : self
    {
        return new self('Inserting empty values array is not supported in ClickHouse');
    }
}
