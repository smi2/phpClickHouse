<?php

namespace ClickHouseDB\Transport;

use ClickHouseDB\Query;
use ClickHouseDB\Settings;
use ClickHouseDB\Statement;
use ClickHouseDB\WhereInFile;
use Curler\CurlerRolling;
use Curler\Request;

/**
 * Class Http like simpleCurl
 * @package ClickHouseDB\Transport
 */
class Http
{
    /**
     * @var string
     */
    private $username = null;

    /**
     * @var string
     */
    private $password = null;

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var int
     */
    private $port = 0;

    /**
     * @var bool
     */
    private $_verbose = false;

    /**
     * @var CurlerRolling
     */
    private $curler = false;

    /**
     * @var Settings
     */
    private $_settings = false;


    /**
     * Http constructor.
     * @param $host
     * @param $port
     * @param $username
     * @param $password
     */
    public function __construct($host, $port, $username, $password)
    {
        $this->setHost($host, $port);

        $this->username = $username;
        $this->password = $password;
        $this->_settings = new Settings($this);

        $this->curler = new CurlerRolling();
        $this->curler->setSimultaneousLimit(10);
    }

    /**
     * @param $host
     * @param int $port
     */
    public function setHost($host, $port = -1)
    {
        if ($port > 0) {
            $this->port = $port;
        }

        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return 'http://' . $this->host . ':' . $this->port;
    }

    /**
     * @param $list_hosts
     * @param $time_out
     * @return array
     */
    public function checkServerReplicas($list_hosts, $time_out)
    {
        // @todo add WHERE database=XXXX

        $query['query'] = 'SELECT * FROM system.replicas FORMAT JSON';
        $query['user'] = $this->username;
        $query['password'] = $this->password;

        $resultGoodHost = [];
        $resultBadHost = [];

        $statements = [];
        foreach ($list_hosts as $host) {
            $request = new Request();
            $url = 'http://' . $host . ":" . $this->port . '?' . http_build_query($query);

            $request->url($url)
                    ->GET()
                    ->verbose(false)
                    ->timeOut($time_out)
                    ->connectTimeOut($time_out)
                    ->setDnsCache(0);

            $this->curler->addQueLoop($request);
            $statements[$host] = new \ClickHouseDB\Statement($request);
        }

        $this->curler->execLoopWait();

        foreach ($statements as $host => $statement) {
            if ($statement->isError()) {
                $resultBadHost[$host] = 1;
            }
            else {
                $result = $statement->rows();
                $flag_bad = false;
//                foreach ($result as $row)
//                {
//                    if (!isset($row['total_replicas']))  $flag_bad=true;
//                    if (!isset($row['active_replicas']))  $flag_bad=true;
//                    if ($row['total_replicas']!==$row['active_replicas'])  $flag_bad=true;
//
//                    if ($flag_bad) break;
//                }


                if ($flag_bad) {
                    $resultBadHost[$host] = $result;
                }
                else {
                    $resultGoodHost[$host] = $result;
                }
            }
        }

        // @todo : use total_replicas + active_replicas - for check state ?
        // total_replicas + active_replicas

        return [$resultGoodHost, $resultBadHost];
    }

    /**
     * @return array
     */
    public function getHostIPs()
    {
        return gethostbynamel($this->host);
    }

    /**
     * @return Settings
     */
    public function settings()
    {
        return $this->_settings;
    }

    /**
     * @param $flag
     * @return mixed
     */
    public function verbose($flag)
    {
        $this->_verbose = $flag;
        return $flag;
    }

    /**
     * @param array $params
     * @return string
     */
    private function getUrl($params = [])
    {
        $settings = $this->settings()->getSettings();

        if (is_array($params) && sizeof($params)) {
            $settings = array_merge($settings, $params);
        }

        return $this->getUri() . '?' . http_build_query($settings);
    }

    /**
     * @param $extendinfo
     * @return Request
     */
    private function newRequest($extendinfo)
    {
        $new = new \Curler\Request();
        $new->auth($this->username, $this->password)
            ->POST()
            ->setRequestExtendedInfo($extendinfo);

        if ($this->settings()->isEnableHttpCompression()) {
            $new->httpCompression(true);
        }

        $new->timeOut($this->settings()->getTimeOut());
        $new->connectTimeOut(1)->keepAlive();// one sec
        $new->verbose($this->_verbose);

        return $new;
    }

    /**
     * @param Query $query
     * @param array $urlParams
     * @param bool $query_as_string
     * @return Request
     */
    private function makeRequest(Query $query, $urlParams = [], $query_as_string = false)
    {
        $sql = $query->toSql();

        if ($query_as_string) {
            $urlParams['query'] = $sql;
        }

        $url = $this->getUrl($urlParams);

        $extendinfo = [
            'sql'   => $sql,
            'query' => $query
        ];

        $new = $this->newRequest($extendinfo);
        $new->url($url);

        if (!$query_as_string) {
            $new->parameters_json($sql);
        }
        if ($this->settings()->isEnableHttpCompression()) {
            $new->httpCompression(true);
        }

        return $new;
    }

    /**
     * @param $sql
     * @param $file_name
     * @return Statement
     */
    public function writeAsyncCSV($sql, $file_name)
    {
        $query = new Query($sql);

        $url = $this->getUrl([
            'readonly' => 0,
            'query' => $query->toSql()
        ]);

        $extendinfo = [
            'sql' => $sql,
            'query' => $query
        ];

        $request = $this->newRequest($extendinfo);
        $request->url($url);

        $request->setCallbackFunction(function (Request $request) {
            fclose($request->getInfileHandle());
        });

        $request->setInfile($file_name);
        $this->curler->addQueLoop($request);

        return new Statement($request);
    }

    /**
     * @return int
     */
    public function getCountPendingQueue()
    {
        return $this->curler->countPending();
    }

    /**
     * @param Query $query
     * @param null $whereInFile
     * @return Request
     */
    public function getRequestRead(Query $query, $whereInFile = null)
    {
        $urlParams = ['readonly' => 1];
        $query_as_string = false;

        if ($whereInFile instanceof WhereInFile && $whereInFile->size()) {
            // $request = $this->prepareSelectWhereIn($request, $whereInFile);
            $structure = $whereInFile->fetchUrlParams();
            // $structure = [];
            $urlParams = array_merge($urlParams, $structure);
            $query_as_string = true;
        }

        // makeRequest read
        $request = $this->makeRequest($query, $urlParams, $query_as_string);

        // attach files
        if ($whereInFile instanceof WhereInFile && $whereInFile->size()) {
            $request->attachFiles($whereInFile->fetchFiles());
        }

        return $request;

    }

    /**
     * @param Query $query
     * @return Request
     */
    public function getRequestWrite(Query $query)
    {
        $urlParams = ['readonly' => 0];
        return $this->makeRequest($query, $urlParams);
    }

    /**
     * @param $sql
     * @param $bindings
     * @param $whereInFile
     * @return Request
     */
    private function prepareSelect($sql, $bindings, $whereInFile)
    {
        $query = new Query($sql, $bindings);
        $query->setFormat('JSON');

        return $this->getRequestRead($query, $whereInFile);

    }

    /**
     * @param $sql
     * @param $bindings
     * @return Request
     */
    private function prepareWrite($sql, $bindings)
    {
        $query = new Query($sql, $bindings);
        return $this->getRequestWrite($query);
    }

    /**
     *
     * @return bool
     */
    public function executeAsync()
    {
        return $this->curler->execLoopWait();
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param null $whereInFile
     * @return Statement
     */
    public function select($sql, array $bindings = [], $whereInFile = null)
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile);
        $code = $this->curler->execOne($request);

        return new Statement($request);
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param null $whereInFile
     * @return Statement
     */
    public function selectAsync($sql, array $bindings = [], $whereInFile = null)
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile);
        $this->curler->addQueLoop($request);
        return new Statement($request);
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param bool $exception
     * @return Statement
     */
    public function write($sql, array $bindings = [], $exception = true)
    {
        $request = $this->prepareWrite($sql, $bindings);
        $code = $this->curler->execOne($request);
        $response = new Statement($request);

        if ($exception) {
            if ($response->isError()) {
                $response->error();
            }
        }

        return $response;
    }
}
