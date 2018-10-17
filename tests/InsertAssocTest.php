<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Class InsertAssocTest
 * @group InsertAssocTest
 * @package ClickHouseDB\Tests
 */
final class InsertAssocTest extends TestCase
{
    use WithClient;

    public function testPrepareOneRow()
    {
        $toInsert = [
            'one' => 1,
            'two' => 2,
            'thr' => 3,
        ];
        $exceptColumns = ['one','two','thr'];
        $exceptValues = [[1,2,3]];
        list($actualColumns, $actualValues) = $this->client->prepareInsertAssocBulk($toInsert);
        $this->assertEquals($exceptValues, $actualValues);
        $this->assertEquals($exceptColumns, $actualColumns);
    }

    public function testPrepareManyRowSuccess()
    {
        $oneRow = [
            'one' => 1,
            'two' => 2,
            'thr' => 3,
        ];
        $toInsert = [$oneRow, $oneRow, $oneRow];
        $exceptColumns = ['one','two','thr'];
        $exceptValues = [[1,2,3],[1,2,3],[1,2,3]];
        list($actualColumns, $actualValues) = $this->client->prepareInsertAssocBulk($toInsert);
        $this->assertEquals($exceptValues, $actualValues);
        $this->assertEquals($exceptColumns, $actualColumns);
    }

    public function testPrepareManyRowFail()
    {
        $oneRow = [
            'one' => 1,
            'two' => 2,
            'thr' => 3,
        ];
        $failRow = [
            'two' => 2,
            'one' => 1,
            'thr' => 3,
        ];
        $toInsert = [$oneRow, $oneRow, $failRow];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Fields not match: two,one,thr and one,two,thr on element 2");

        list($_, $__) = $this->client->prepareInsertAssocBulk($toInsert);
    }
}
