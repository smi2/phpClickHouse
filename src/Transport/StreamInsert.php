<?php

namespace ClickHouseDB\Transport;

use Curler\Request;
use Curler\CurlerRolling;
use ClickHouseDB\Statement;

/**
 * Class StreamInsert
 * @package ClickHouseDB\Transport
 */
class StreamInsert
{
    /**
     * @var resource
     */
    private $source;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CurlerRolling
     */
    private $curlerRolling;

    /**
     * StreamInsert constructor.
     * @param resource $source
     * @param Request $request
     * @param \Curler\CurlerRolling $curlerRolling
     */
    public function __construct($source, Request $request, CurlerRolling $curlerRolling)
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Argument $source must be resource');
        }
        $this->source = $source;
        $this->request = $request;
        $this->curlerRolling = $curlerRolling;
    }

    /**
     * @param callable $callback function for stream read data
     * @return \ClickHouseDB\Statement
     * @throws \Exception
     */
    public function insert($callback)
    {
        try {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('Argument $callback can not be called as a function');
            }

            $this->request->header('Transfer-Encoding', 'chunked');
            $this->request->setReadFunction($callback);
            $this->curlerRolling->execOne($this->request, true);
            $statement = new Statement($this->request);
            $statement->error();
            return $statement;
        } finally {
            fclose($this->source);
        }
    }
}