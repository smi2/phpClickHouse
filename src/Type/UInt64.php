<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use Stringable;

final class UInt64 implements NumericType, Stringable
{
    public string $value;

    private function __construct(string $uint64Value)
    {
        $this->value = $uint64Value;
    }

    /**
     * The value is emitted into SQL without escaping — pass trusted data only.
     *
     * @psalm-taint-sink sql $uint64Value
     */
    public static function fromString(string $uint64Value): self
    {
        return new self($uint64Value);
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
