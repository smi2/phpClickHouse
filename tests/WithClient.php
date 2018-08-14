<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use function getenv;
use function sprintf;

trait WithClient
{
    /** @var Client */
    private $client;

    private $tmpPath;

    /**
     * @before
     */
    public function setupClickHouseClient()
    {
        $this->restartClickHouseClient();
        $this->tmpPath = getenv('CLICKHOUSE_TMPPATH') . DIRECTORY_SEPARATOR;
    }

    public function restartClickHouseClient()
    {
        $config       = [
            'host'     => getenv('CLICKHOUSE_HOST'),
            'port'     => getenv('CLICKHOUSE_PORT'),
            'username' => getenv('CLICKHOUSE_USER'),
            'password' => getenv('CLICKHOUSE_PASSWORD'),

        ];
        $this->client = new Client($config);
        $databaseName = getenv('CLICKHOUSE_DATABASE');
        $this->client->write(sprintf('DROP DATABASE IF EXISTS "%s"', $databaseName));
        $this->client->write(sprintf('CREATE DATABASE "%s"', $databaseName));
        $this->client->database($databaseName);
    }
}
