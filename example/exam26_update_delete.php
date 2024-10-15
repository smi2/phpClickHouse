<?php

use ClickHouseDB\Query\Query;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Client;

include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';


echo "\nPrepare....\n";
$client = new ClickHouseDB\Client($config);
$client->ping();
echo "OK!\n";

// Create a Query object
$query = new Query('SELECT * FROM test_table');

try {
    // Example of a successful delete
    $table = 'test_table';
    $condition = 'id = 1';
    
    $deleteSql = $query->delete($table, $condition);
    echo "Delete SQL: " . $deleteSql . PHP_EOL; // Outputs: Delete SQL: DELETE FROM test_table WHERE id = 1

    // Execute the delete query using the client (assuming a method exists)
    // $client->write($deleteSql);

} catch (QueryException $e) {
    echo "Error deleting record: " . $e->getMessage() . PHP_EOL;
}

// Example of handling an error case
try {
    // Attempting to delete with an empty table name
    $table = '';
    $condition = 'id = 1';
    
    $query->delete($table, $condition);
} catch (QueryException $e) {
    echo "Error deleting record: " . $e->getMessage() . PHP_EOL; // Outputs: Error deleting record: Invalid parameters for delete
}



// Create a Query object
$query = new Query('SELECT * FROM test_table');

try {
    // Example of a successful update
    $data = ['column1' => 'newValue', 'column2' => 42];
    $table = 'test_table';
    $condition = 'id = 1';
    
    $updateSql = $query->update($data, $table, $condition);
    echo "Update SQL: " . $updateSql . PHP_EOL; // Outputs: Update SQL: UPDATE test_table SET column1 = :column1, column2 = :column2 WHERE id = 1

    // Execute the update query using the client (assuming a method exists)
    // $client->write($updateSql, $data);

} catch (QueryException $e) {
    echo "Error updating record: " . $e->getMessage() . PHP_EOL;
}

// Example of handling an error case
try {
    // Attempting to update with empty data
    $data = [];
    $table = 'test_table';
    $condition = 'id = 1';
    
    $query->update($data, $table, $condition);
} catch (QueryException $e) {
    echo "Error updating record: " . $e->getMessage() . PHP_EOL; // Outputs: Error updating record: Invalid parameters for update
}
