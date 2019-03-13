<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Query\Expression;

use ClickHouseDB\Query\Expression;
use ClickHouseDB\Quote\FormatLine;
use PHPUnit\Framework\TestCase;

final class RawTest extends TestCase
{
    public function testNeedsEncoding() : void
    {
        self::assertEquals(
            false,
            (new Expression\Raw(''))->needsEncoding()
        );
    }
    public function testGetValue() : void
    {
        $expressionString = "UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')";
        $expressionObject = new Expression\Raw($expressionString);

        self::assertEquals(
            $expressionString,
            $expressionObject->getValue()
        );
    }

    public function testExpressionValueForInsert() : void
    {
        $expressionString = "UUIDStringToNum('0f372656-6a5b-4727-a4c4-f6357775d926')";
        $preparedValue    = FormatLine::Insert([new Expression\Raw($expressionString)]);

        self::assertEquals(
            $expressionString,
            $preparedValue
        );
    }
}
