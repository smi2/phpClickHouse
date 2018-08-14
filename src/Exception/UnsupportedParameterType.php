<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

use InvalidArgumentException;
use function gettype;
use function sprintf;

final class UnsupportedParameterType extends InvalidArgumentException implements ClickHouseException
{
    /**
     * @param mixed $parameter
     */
    public static function new($parameter) : self
    {
        return new self(sprintf('Parameter of type "%s" cannot be bound', gettype($parameter)));
    }
}
