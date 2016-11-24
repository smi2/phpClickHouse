<?php
namespace ClickHouseDB\Transport;
class StreamInsert
{
    /**
     * @var \Curler\Request
     */
    private $insert;
    /**
     * @var resource
     */
    private $read;

    /**
     * @var \Curler\CurlerRolling
     */
    private $roll;

    public function __construct($resource_read,\Curler\Request $insert_request,\Curler\CurlerRolling $roll)
    {
        $this->read=$resource_read;

        $this->insert=$insert_request;

        $this->roll=$roll;
    }

    /**
     *
     * @return \ClickHouseDB\Statement
     */
    public function insert()
    {
        $read_stream=$this->read;

        $this->insert->header('Transfer-Encoding','chunked');
        $this->insert->setReadFunction(function ($ch, $fd, $length) use ($read_stream) {
            $d=fread($read_stream, $length);
            return  ($d?$d:"");
        });
        $this->insert->setCallbackFunction(function (\Curler\Request $request)  use ($read_stream)  {
            fclose($read_stream);
        });

        $this->roll->addQueLoop($this->insert);
        $this->roll->execLoopWait();

        $state=new \ClickHouseDB\Statement($this->insert);
        $state->error();
        return $state;
    }
}