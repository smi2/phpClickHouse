<?php

use PHPUnit\Framework\TestCase;
use ClickHouseDB\Query\Query;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Client;

class QueryTest extends TestCase
{
    protected $query;
    protected $client;

    protected function setUp(): void
    {
        // Initialize ClickHouse client
        $this->client = new Client([
            'host' => 'localhost',
            'port' => 8123,
            'username' => 'default',
            'password' => '',
            'database' => 'test_db'
        ]);

        // Create a test table and insert initial data
        $this->client->write('CREATE TABLE IF NOT EXISTS test_table (id UInt32, column1 String, column2 UInt32) ENGINE = MergeTree() ORDER BY id');
        $this->client->write('INSERT INTO test_table (id, column1, column2) VALUES (1, "value1", 10)');

        // Initialize the Query object
        $this->query = new Query('SELECT * FROM test_table');
    }

    protected function tearDown(): void
    {
        // Clean up the test table after each test
        $this->client->write('DROP TABLE IF EXISTS test_table');
    }

    public function testUpdate()
    {
        // Arrange
        $data = ['column1' => 'newValue', 'column2' => 42];
        $table = 'test_table';
        $condition = 'id = 1';

        // Act
        $result = $this->query->update($data, $table, $condition);

        // Assert
        $expectedSql = "UPDATE $table SET column1 = :column1, column2 = :column2 WHERE $condition";
        $this->assertEquals($expectedSql, $result);
    }

    public function testDelete()
    {
        // Arrange
        $table = 'test_table';
        $condition = 'id = 1';

        // Act
        $result = $this->query->delete($table, $condition);

        // Assert
        $expectedSql = "DELETE FROM $table WHERE $condition";
        $this->assertEquals($expectedSql, $result);
    }

    public function testUpdateWithEmptyData()
    {
        // Arrange
        $data = [];
        $table = 'test_table';
        $condition = 'id = 1';

        // Assert
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid parameters for update');

        // Act
        $this->query->update($data, $table, $condition);
    }

    public function testUpdateWithEmptyTable()
    {
        // Arrange
        $data = ['column1' => 'newValue'];
        $table = '';
        $condition = 'id = 1';

        // Assert
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid parameters for update');

        // Act
        $this->query->update($data, $table, $condition);
    }

    public function testUpdateWithEmptyCondition()
    {
        // Arrange
        $data = ['column1' => 'newValue'];
        $table = 'test_table';
        $condition = '';

        // Assert
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid parameters for update');

        // Act
        $this->query->update($data, $table, $condition);
    }

    public function testDeleteWithEmptyTable()
    {
        // Arrange
        $table = '';
        $condition = 'id = 1';

        // Assert
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid parameters for delete');

        // Act
        $this->query->delete($table, $condition);
    }

    public function testDeleteWithEmptyCondition()
    {
        // Arrange
        $table = 'test_table';
        $condition = '';

        // Assert
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid parameters for delete');

        // Act
        $this->query->delete($table, $condition);
    }
}