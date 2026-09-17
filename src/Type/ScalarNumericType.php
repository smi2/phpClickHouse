<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use Stringable;

interface ScalarNumericType extends NumericType, Stringable
{
    public static function fromString(string $value): static;

    public function getValue(): string;
}
