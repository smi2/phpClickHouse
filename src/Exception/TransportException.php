<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

use LogicException;

final class TransportException extends LogicException implements ClickHouseException
{
}
