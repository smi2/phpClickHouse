<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;

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
    }
}
