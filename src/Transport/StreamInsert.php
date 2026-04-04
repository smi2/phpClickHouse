<?php

namespace ClickHouseDB\Transport;

use ClickHouseDB\Statement;

/**
 * Class StreamInsert
 * @deprecated
 * @package ClickHouseDB\Transport
 */
class StreamInsert
{
    private $source;

    private $request;

    private $curlerRolling;

    public function __construct($source, CurlerRequest $request, ?CurlerRolling $curlerRolling = null)
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Argument $source must be resource');
        }
        if ($curlerRolling instanceof CurlerRolling)
        {
            $this->curlerRolling = $curlerRolling;
        } else {
            $this->curlerRolling = new CurlerRolling();
        }
        $this->source = $source;
        $this->request = $request;
    }

    /**
     * @throws \Exception
     */
    public function insert(callable $callback): \ClickHouseDB\Statement
    {
        try {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('Argument $callback can not be called as a function');
            }

            //
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
