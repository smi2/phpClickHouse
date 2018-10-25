<?php

declare(strict_types=1);

namespace ClickHouseDB\Query\Expression;

/**
 * Pass expression "as is" to be sent and executed at server.
 * P.ex.: `new Expression\Function\UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926');`
 */
class UUIDStringToNum implements Expression
{
    /** @var string */
    private $uuid;

    public function __construct(string $uuid)
    {
        $this->$uuid = $uuid;
    }

    public function needsEncoding() : bool
    {
        return false;
    }

    public function getValue() : string
    {
        return "UUIDStringToNum('{$this->uuid}')";
    }
}
