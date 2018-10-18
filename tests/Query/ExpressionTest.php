<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Query;

use ClickHouseDB\Query\Expression;
use ClickHouseDB\Quote\FormatLine;
use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    public function testToString()
    {
        $expressionString = "UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')";
        $expr = new Expression($expressionString);

        $this->assertEquals(
            $expressionString,
            (string) $expr
        );
    }

    public function testExpressionValueForInsert()
    {
        $expressionString = "UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')";
        $preparedValue = FormatLine::Insert([new Expression($expressionString)]);

        $this->assertEquals(
            $expressionString,
            $preparedValue
        );
    }
}