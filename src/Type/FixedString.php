<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use InvalidArgumentException;
use Stringable;

use function strlen;

final class FixedString implements StringValue, Stringable
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value, int $length): self
    {
        if ($length < 1 || strlen($value) !== $length) {
            throw new InvalidArgumentException('FixedString requires a positive byte length equal to the value length.');
        }

        return new self($value);
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
