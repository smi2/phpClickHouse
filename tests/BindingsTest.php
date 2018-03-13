<?php

use PHPUnit\Framework\TestCase;

/**
 * Class BindingsTest
 */
class BindingsTest extends TestCase
{
    /**
     * @return array
     */
    public function escapeDataProvider()
    {
        return [
            [
                'select * from test. WHERE id = :id',
                ['id' => 1],
                'select * from test. WHERE id = 1',
            ],
            [
                'select * from test. WHERE id = :id',
                ['id' => '1'],
                "select * from test. WHERE id = '1'",
            ],
            [
                'select * from test. WHERE id IN (:id)',
                ['id' => ['1', 2]],
                "select * from test. WHERE id IN ('1','2')",
            ],
            [
                'select * from test. WHERE id IN (:id)',
                ['id' => ['1', "2') OR ('1'='1"]],
                "select * from test. WHERE id IN ('1','2\') OR (\'1\'=\'1')",
            ],
            [
                'select * from test. WHERE id = :id',
                ['id' => "2' OR (1=1)"],
                "select * from test. WHERE id = '2\' OR (1=1)'",
            ],
        ];
    }

    /**
     * @param string $sql Given SQL
     * @param array $params Params
     * @param string $expectedSql Expected SQL
     * @dataProvider escapeDataProvider
     */
    public function testEscape($sql, $params, $expectedSql)
    {

        $bindings = new \ClickHouseDB\Query\Degeneration\Bindings();
        $bindings->bindParams($params);
        $sql = $bindings->process($sql);
        $this->assertSame($expectedSql, $sql);
    }
}
