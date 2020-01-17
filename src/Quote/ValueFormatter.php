<?php

declare(strict_types=1);

namespace ClickHouseDB\Quote;

use ClickHouseDB\Exception\UnsupportedValueType;
use ClickHouseDB\Query\Expression\Expression;
use ClickHouseDB\Type\Type;
use DateTimeInterface;
use function addslashes;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;

class ValueFormatter
{
    /**
     * @param mixed $value
     * @param bool $addQuotes
     * @return mixed
     */
    public static function formatValue($value, bool $addQuotes = true)
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if (is_float($value) || is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if ($value instanceof Type) {
            return $value->getValue();
        }

        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if (is_object($value) && is_callable([$value, '__toString'])) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            if ($addQuotes) {
                return self::formatStringParameter(self::escapeString($value));
            }

            return $value;
        }

        throw UnsupportedValueType::new($value);
    }

    /**
     * Escape an string
     *
     * @param string $value
     * @return string
     */
    private static function escapeString($value)
    {
        return addslashes($value);
    }

    /**
     * @return string
     */
    private static function formatStringParameter($value)
    {
        return sprintf("'%s'", $value);
    }
}
