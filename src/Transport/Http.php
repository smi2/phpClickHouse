<?php

namespace ClickHouseDB\Transport;

use ClickHouseDB\Query;
use ClickHouseDB\Settings;
use ClickHouseDB\Statement;
use ClickHouseDB\WhereInFile;
use ClickHouseDB\WriteToFile;
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
    private $_username = null;

    /**
     * @var string
     */
    private $_password = null;

    /**
     * @var string
     */
    private $_host = '';

    /**
     * @var int
     */
    private $_port = 0;

    /**
     * @var bool
     */
    private $_verbose = false;

    /**
     * @var CurlerRolling
     */
    private $_curler = false;

    /**
     * @var Settings
     */
    private $_settings = false;

    /**
     * @var array
     */
    private $_query_degenerations = [];

    /**
     * Количество секунд ожидания при попытке соединения
     *
     * @var int
     */
    private $_connectTimeOut = 5;

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

        $this->_username = $username;
        $this->_password = $password;
        $this->_settings = new Settings($this);

        $this->setCurler();
    }


    public function setCurler()
    {
        $this->_curler = new CurlerRolling();
    }

    /**
     * @return CurlerRolling
     */
    public function getCurler()
    {
        return $this->_curler;
    }

    /**
     * @param $host
     * @param int $port
     */
    public function setHost($host, $port = -1)
    {
        if ($port > 0) {
            $this->_port = $port;
        }

        $this->_host = $host;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return 'http://' . $this->_host . ':' . $this->_port;
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


        if ($this->settings()->isReadOnlyUser())
        {
            unset($settings['extremes']);
            unset($settings['readonly']);
            unset($settings['enable_http_compression']);
            unset($settings['max_execution_time']);

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
        $new->auth($this->_username, $this->_password)
            ->POST()
            ->setRequestExtendedInfo($extendinfo);

        if ($this->settings()->isEnableHttpCompression()) {
            $new->httpCompression(true);
        }

        $new->timeOut($this->settings()->getTimeOut());
        $new->connectTimeOut($this->_connectTimeOut)->keepAlive();// one sec
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
            'sql' => $sql,
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
     * @param $stream
     * @return Request
     */
    public function writeStreamData($sql)
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
        return $request;
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
        $this->_curler->addQueLoop($request);

        return new Statement($request);
    }

    /**
     * @return int
     */
    public function getCountPendingQueue()
    {
        return $this->_curler->countPending();
    }

    /**
     * Количество секунд ожидания
     *
     * @param int $connectTimeOut
     */
    public function setConnectTimeOut($connectTimeOut)
    {
        $this->_connectTimeOut = $connectTimeOut;
    }

    /**
     * Количество секунд ожидания
     *
     * @return int
     */
    public function getConnectTimeOut()
    {
        return $this->_connectTimeOut;
    }

    /**
     * @param Query $query
     * @param null $whereInFile
     * @return Request
     */
    public function getRequestRead(Query $query, $whereInFile = null, $writeToFile = null)
    {
        $urlParams = ['readonly' => 1];
        $query_as_string = false;
        // ---------------------------------------------------------------------------------
        if ($whereInFile instanceof WhereInFile && $whereInFile->size()) {
            // $request = $this->prepareSelectWhereIn($request, $whereInFile);
            $structure = $whereInFile->fetchUrlParams();
            // $structure = [];
            $urlParams = array_merge($urlParams, $structure);
            $query_as_string = true;
        }
        // ---------------------------------------------------------------------------------
        // if result to file
        if ($writeToFile instanceof WriteToFile && $writeToFile->fetchFormat()) {
            $query->setFormat($writeToFile->fetchFormat());
            unset($urlParams['extremes']);
        }
        // ---------------------------------------------------------------------------------
        // makeRequest read
        $request = $this->makeRequest($query, $urlParams, $query_as_string);
        // ---------------------------------------------------------------------------------
        // attach files
        if ($whereInFile instanceof WhereInFile && $whereInFile->size()) {
            $request->attachFiles($whereInFile->fetchFiles());
        }
        // ---------------------------------------------------------------------------------
        // result to file
        if ($writeToFile instanceof WriteToFile && $writeToFile->fetchFormat()) {

            $fout = fopen($writeToFile->fetchFile(), 'w');
            $isGz = $writeToFile->getGzip();

            if ($isGz) {
                // write gzip header
//                "\x1f\x8b\x08\x00\x00\x00\x00\x00"
//                fwrite($fout, "\x1F\x8B\x08\x08".pack("V", time())."\0\xFF", 10);
                fwrite($fout, "\x1f\x8b\x08\x00\x00\x00\x00\x00");
                // write the original file name
//                $oname = str_replace("\0", "", basename($writeToFile->fetchFile()));
//                fwrite($fout, $oname."\0", 1+strlen($oname));

            }


            $request->setResultFileHandle($fout, $isGz)->setCallbackFunction(function (Request $request) {
                fclose($request->getResultFileHandle());
            });
        }
        // ---------------------------------------------------------------------------------
        return $request;

    }

    public function cleanQueryDegeneration()
    {
        $this->_query_degenerations = [];
        return true;
    }

    public function addQueryDegeneration(Query\Degeneration $degeneration)
    {
        $this->_query_degenerations[] = $degeneration;
        return true;
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
     * @return Query
     */
    private function prepareQuery($sql, $bindings)
    {

        // add Degeneration query
        foreach ($this->_query_degenerations as $degeneration) {
            $degeneration->bindParams($bindings);
        }

        return new Query($sql, $this->_query_degenerations);
    }

    /**
     * @param $sql
     * @param $bindings
     * @param $whereInFile
     * @return Request
     */
    private function prepareSelect($sql, $bindings, $whereInFile, $writeToFile = null)
    {
        if ($sql instanceof Query) {
            return $this->getRequestWrite($sql);
        }


        $query = $this->prepareQuery($sql, $bindings);
        $query->setFormat('JSON');
        return $this->getRequestRead($query, $whereInFile, $writeToFile);

    }

    /**
     * @param $sql
     * @param $bindings
     * @return Request
     */
    private function prepareWrite($sql, $bindings = [])
    {
        if ($sql instanceof Query) {
            return $this->getRequestWrite($sql);
        }

        $query = $this->prepareQuery($sql, $bindings);
        return $this->getRequestWrite($query);
    }

    /**
     *
     * @return bool
     */
    public function executeAsync()
    {
        return $this->_curler->execLoopWait();
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param null $whereInFile
     * @return Statement
     */
    public function select($sql, array $bindings = [], $whereInFile = null, $writeToFile = null)
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile, $writeToFile);
        $code = $this->_curler->execOne($request);

        return new Statement($request);
    }

    /**
     * @param $sql
     * @param array $bindings
     * @param null $whereInFile
     * @return Statement
     */
    public function selectAsync($sql, array $bindings = [], $whereInFile = null, $writeToFile = null)
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile, $writeToFile);
        $this->_curler->addQueLoop($request);
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
        $code = $this->_curler->execOne($request);

        $response = new Statement($request);
        if ($exception) {
            if ($response->isError()) {
                $response->error();
            }
        }
        return $response;
    }
}
