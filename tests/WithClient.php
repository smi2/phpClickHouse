<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use function getenv;
use function sprintf;
use const DIRECTORY_SEPARATOR;

trait WithClient
{
    /** @var Client */
    private $client;

    private $tmpPath;

    /** @var string */
    private $currentDbName = 'php_clickhouse_client_test_db';

    /**
     * @before
     */
    public function setupClickHouseClient()
    {
        $this->restartClickHouseClient();
        $this->tmpPath = getenv('CLICKHOUSE_TMPPATH') . DIRECTORY_SEPARATOR;
    }

    public function restartClickHouseClient() : void
    {
        $databaseName = getenv('CLICKHOUSE_DATABASE');
        $config       = [
            'host'     => getenv('CLICKHOUSE_HOST'),
            'port'     => (int) getenv('CLICKHOUSE_PORT'),
            'username' => getenv('CLICKHOUSE_USER'),
            'password' => getenv('CLICKHOUSE_PASSWORD'),
            'database' => $databaseName,
        ];

        $this->client = new Client($config);

//        if (empty($GLOBALS['phpCH_needFirstCreateDB'])) { // hack use Global VAR, for once create DB
//            $GLOBALS['phpCH_needFirstCreateDB'] = true;
        $this->client->write(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
        $this->client->write(sprintf('CREATE DATABASE "%s"', $this->currentDbName));
//        }
        $this->client->setDatabase($this->currentDbName);
    }

    /**
     * @after
     */
    public function tearDownDataBase() : void
    {

    }
}
