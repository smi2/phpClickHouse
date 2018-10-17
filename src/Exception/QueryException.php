<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

use LogicException;

class QueryException extends LogicException implements ClickHouseException
{
}
