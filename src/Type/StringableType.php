<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

/** A raw string value that must be escaped when used as an SQL literal. */
interface StringableType extends Type
{
    public function getValue(): string;
}
