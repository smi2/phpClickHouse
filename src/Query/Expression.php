<?php

namespace ClickHouseDB\Query;

class Expression
{
    /**
     * @var string
     */
    private $expression;

    /**
     * Expression constructor.
     * Pass expression "as is" it should be sent and executed at server.
     * P.ex.: `new Expression("UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')");`
     *
     * @param string $expression
     */
    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->expression;
    }
}