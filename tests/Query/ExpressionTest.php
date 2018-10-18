<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Query;

use ClickHouseDB\Query\Expression;
use ClickHouseDB\Quote\FormatLine;
use PHPUnit\Framework\TestCase;

final class ExpressionTest extends TestCase
{
    public function testToString() : void
    {
        $expressionString = "UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')";
        $expressionObject = new Expression($expressionString);

        self::assertEquals(
            $expressionString,
            (string) $expressionObject
        );
    }

    public function testExpressionValueForInsert() : void
    {
        $expressionString = "UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')";
        $preparedValue    = FormatLine::Insert([new Expression($expressionString)]);

        self::assertEquals(
            $expressionString,
            $preparedValue
        );
    }
}
