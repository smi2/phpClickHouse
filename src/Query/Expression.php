<?php

declare(strict_types=1);
namespace ClickHouseDB\Query;

/**
 * Pass expression "as is" to be sent and executed at server.
 * P.ex.: `new Expression("UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')");`
 */
class Expression
{
    /** @var string */
    private $expression;

    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
