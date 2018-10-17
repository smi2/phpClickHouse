<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

final class UInt64 implements NumericType
{
    /** @var string */
    public $value;

    private function __construct(string $uint64Value)
    {
        $this->value = $uint64Value;
    }

    /**
     * @return self
     */
    public static function fromString(string $uint64Value)
    {
        return new self($uint64Value);
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}
