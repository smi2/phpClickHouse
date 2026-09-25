<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use InvalidArgumentException;

use function is_numeric;

final class Decimal32 implements ScalarNumericType
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): static
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Decimal32 expects a numeric string, got: ' . $value);
        }

        return new static($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
