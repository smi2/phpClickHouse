<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use InvalidArgumentException;

use function preg_match;

final class Int32 implements ScalarNumericType
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): static
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('Int32 expects an integer string, got: ' . $value);
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
