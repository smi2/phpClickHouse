<?php

declare(strict_types=1);

namespace ClickHouseDB;
use ClickHouseDB\Exception\QueryException;

/**
 * Class NewClient
 * @package ClickHouseDB
 */
class NewClient extends Client
{
    protected $request;

    public function __construct(array $connectParams, array $settings = [])
    {

        // Call the parent constructor if it exists
        parent::__construct($connectParams, $settings );
    }
    
    public function update($data, $table, $condition)
    {
        if (empty($data) || empty($table) || empty($condition)) {
            throw new QueryException('Invalid parameters for update');
        }

        $setClause = [];
        foreach ($data as $key => $value) {
            $setClause[] = "$key = :$key"; 
        }
        $setClause = implode(', ', $setClause);
        $sql = "UPDATE $table SET $setClause WHERE $condition";

        return $sql; 
    }

    public function delete(string $table, string $condition)
    {

        if (empty($table) || empty($condition)) {
            throw new QueryException('Invalid parameters for delete');
        }

        $sql = "DELETE FROM $table WHERE $condition";

        return $sql; //
    }


    // Add any other methods required by the \Client interface
}

