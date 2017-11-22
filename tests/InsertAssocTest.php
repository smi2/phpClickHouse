<?php
/**
 * Created by PhpStorm.
 * User: maelstorm
 * Date: 21.11.17
 * Time: 15:29
 */

use PHPUnit\Framework\TestCase;

class InsertAssocTest extends TestCase
{
    /**
     * @var \ClickHouseDB\Client
     */
    private $db;

    /**
     * @throws Exception
     */
    public function setUp()
    {
        //на самом деле настоящие запросы мы проводить не будем
        //тестируем не клиент (у него есть свои тесты), а подготовку данных
        $config = [
            'host'     => 'localhost',
            'port'     => 9000,
            'username' => 'default',
            'password' => ''
        ];

        $this->db = new ClickHouseDB\Client($config);
    }

    /**
     *
     */
    public function tearDown()
    {
        //
    }

    public function testPrepareOneRow()
    {
        $toInsert = [
            'one' => 1,
            'two' => 2,
            'thr' => 3,
        ];
        $exceptColumns = ['one','two','thr'];
        $exceptValues = [[1,2,3]];
        list($actualColumns, $actualValues) = $this->db->prepareInsertAssocBulk($toInsert);
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
        list($actualColumns, $actualValues) = $this->db->prepareInsertAssocBulk($toInsert);
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
        $this->expectException(\ClickHouseDB\QueryException::class);
        $this->expectExceptionMessage("Fields not match: two,one,thr and one,two,thr on element 2");
        list($_, $__) = $this->db->prepareInsertAssocBulk($toInsert);
    }
}