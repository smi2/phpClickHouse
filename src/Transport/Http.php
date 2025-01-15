<?php

namespace ClickHouseDB\Transport;

use ClickHouseDB\Exception\TransportException;
use ClickHouseDB\Query\Degeneration;
use ClickHouseDB\Query\Query;
use ClickHouseDB\Query\WhereInFile;
use ClickHouseDB\Query\WriteToFile;
use ClickHouseDB\Settings;
use ClickHouseDB\Statement;
use const PHP_EOL;

class Http
{
    const AUTH_METHOD_HEADER       = 1;
    const AUTH_METHOD_QUERY_STRING = 2;
    const AUTH_METHOD_BASIC_AUTH   = 3;

    const AUTH_METHODS_LIST = [
        self::AUTH_METHOD_HEADER,
        self::AUTH_METHOD_QUERY_STRING,
        self::AUTH_METHOD_BASIC_AUTH,
    ];

    /**
     * @var string
     */
    private $_username = null;

    /**
     * @var string
     */
    private $_password = null;

    /**
     * The username and password can be indicated in one of three ways:
     *  - Using HTTP Basic Authentication.
     *  - In the ‘user’ and ‘password’ URL parameters.
     *  - Using ‘X-ClickHouse-User’ and ‘X-ClickHouse-Key’ headers (by default)
     *
     * @see https://clickhouse.tech/docs/en/interfaces/http/
     * @var int
     */
    private $_authMethod = self::AUTH_METHOD_HEADER;

    /**
     * @var string
     */
    private $_host = '';

    /**
     * @var int
     */
    private $_port = 0;

    /**
     * @var bool|int
     */
    private $_verbose = false;

    /**
     * @var CurlerRolling
     */
    private $_curler = null;

    /**
     * @var Settings
     */
    private $_settings = null;

    /**
     * @var array
     */
    private $_query_degenerations = [];

    /**
     * Count seconds (int)
     *
     * @var float
     */
    private $_connectTimeOut = 5.0;

    /**
     * @var callable
     */
    private $xClickHouseProgress = null;

    /**
     * @var null|string
     */
    private $sslCA = null;

    /**
     * @var null|resource
     */
    private $stdErrOut = null;

    /**
     * @var null|resource
     */
    private $handle = null;

    /**
     * Http constructor.
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param int $authMethod
     */
    public function __construct($host, $port, $username, $password, $authMethod = null)
    {
        $this->setHost($host, $port);

        $this->_username = $username;
        $this->_password = $password;
        if ($authMethod) {
            $this->_authMethod = $authMethod;
        }

        $this->_settings = new Settings();

        $this->setCurler();
    }


    public function setCurler() : void
    {
        $this->_curler = new CurlerRolling();
    }

    /**
     * @param CurlerRolling $curler
     */
    public function setDirtyCurler(CurlerRolling $curler) : void
    {
        if ($curler instanceof CurlerRolling) {
            $this->_curler = $curler;
        }
    }

    /**
     * @return CurlerRolling
     */
    public function getCurler(): ?CurlerRolling
    {
        return $this->_curler;
    }

    /**
     * @param string $host
     * @param int $port
     */
    public function setHost(string $host, int $port = -1) : void
    {
        if ($port > 0) {
            $this->_port = $port;
        }

        $this->_host = $host;
    }

    /**
     * Sets client SSL certificate for Yandex Cloud
     *
     * @param string $caPath
     */
    public function setSslCa(string $caPath) : void
    {
        $this->sslCA = $caPath;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        $proto = 'http';
        if ($this->settings()->isHttps()) {
            $proto = 'https';
        }
        $uri = $proto . '://' . $this->_host;
        if (stripos($this->_host, '/') !== false || stripos($this->_host, ':') !== false) {
            return $uri;
        }
        if (intval($this->_port) > 0) {
            return $uri . ':' . $this->_port;
        }
        return $uri;
    }

    /**
     * @return Settings
     */
    public function settings(): Settings
    {
        return $this->_settings;
    }

    /**
     * @param bool $flag
     * @return bool
     */
    public function verbose(bool $flag): bool
    {
        $this->_verbose = $flag;
        return $flag;
    }

    /**
     * @param array $params
     * @return string
     */
    private function getUrl($params = []): string
    {
        $settings = $this->settings()->getSettings();

        if (is_array($params) && sizeof($params)) {
            $settings = array_merge($settings, $params);
        }


        if ($this->settings()->isReadOnlyUser()) {
            unset($settings['extremes']);
            unset($settings['readonly']);
            unset($settings['enable_http_compression']);
            unset($settings['max_execution_time']);

        }

        unset($settings['https']);


        return $this->getUri() . '?' . http_build_query($settings);
    }

    /**
     * @param array $extendinfo
     * @return CurlerRequest
     */
    private function newRequest($extendinfo): CurlerRequest
    {
        $new = new CurlerRequest();

        switch ($this->_authMethod) {
            case self::AUTH_METHOD_QUERY_STRING:
                /* @todo: Move this implementation to CurlerRequest class. Possible options: the authentication method
                 *        should be applied in method `CurlerRequest:prepareRequest()`.
                 */
                $this->settings()->set('user', $this->_username);
                $this->settings()->set('password', $this->_password);
                break;
            case self::AUTH_METHOD_BASIC_AUTH:
                $new->authByBasicAuth($this->_username, $this->_password);
                break;
            default:
                // Auth with headers by default
                $new->authByHeaders($this->_username, $this->_password);
                break;
        }

        $new->POST()->setRequestExtendedInfo($extendinfo);

        $new->httpCompression($this->settings()->isEnableHttpCompression());

        if ($this->settings()->getSessionId()) {
            $new->persistent();
        }
        if ($this->sslCA) {
            $new->setSslCa($this->sslCA);
        }

        $new->timeOut($this->settings()->getTimeOut());
        $new->connectTimeOut($this->_connectTimeOut);
        $new->keepAlive();
        $new->verbose(boolval($this->_verbose));

        return $new;
    }

    /**
     * @param Query $query
     * @param array $urlParams
     * @param bool $query_as_string
     * @return CurlerRequest
     * @throws \ClickHouseDB\Exception\TransportException
     */
    private function makeRequest(Query $query, array $urlParams = [], bool $query_as_string = false): CurlerRequest
    {
        $sql = $query->toSql();

        if ($query_as_string) {
            $urlParams['query'] = $sql;
        }

        $extendInfo = [
            'sql' => $sql,
            'query' => $query,
            'format' => $query->getFormat()
        ];

        $new = $this->newRequest($extendInfo);

        /*
         * Build URL after request making, since URL may contain auth data. This will not matter after the
         * implantation of the todo in the `HTTP:newRequest()` method.
         */

        if ($query->isUseInUrlBindingsParams()) {
            $urlParams = array_replace_recursive($urlParams, $query->getUrlBindingsParams());
        }

        $url = $this->getUrl($urlParams);
        $new->url($url);

        if (!$query_as_string) {
            $new->parameters_json($sql);
        }
        $new->httpCompression($this->settings()->isEnableHttpCompression());

        return $new;
    }

    /**
     * @param resource $stream
     * @return void
     */
    public function setStdErrOut($stream)
    {
        if (is_resource($stream)) {
            $this->stdErrOut=$stream;
        }

    }

    /**
     * @param string|Query $sql
     * @return CurlerRequest
     */
    public function writeStreamData($sql): CurlerRequest
    {

        if ($sql instanceof Query) {
            $query = $sql;
        } else {
            $query = new Query($sql);
        }

        $extendInfo = [
            'sql' => $sql,
            'query' => $query,
            'format' => $query->getFormat()
        ];

        $request = $this->newRequest($extendInfo);

        /*
         * Build URL after request making, since URL may contain auth data. This will not matter after the
         * implantation of the todo in the `HTTP:newRequest()` method.
         */
        $url = $this->getUrl([
            'readonly' => 0,
            'query' => $query->toSql()
        ]);

        $request->url($url);
        return $request;
    }


    /**
     * @param string $sql
     * @param string $file_name
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function writeAsyncCSV($sql, $file_name): Statement
    {
        $query = new Query($sql);

        $extendinfo = [
            'sql' => $sql,
            'query' => $query,
            'format' => $query->getFormat()
        ];

        $request = $this->newRequest($extendinfo);

        /*
         * Build URL after request making, since URL may contain auth data. This will not matter after the
         * implantation of the todo in the `HTTP:newRequest()` method.
         */
        $url = $this->getUrl([
            'readonly' => 0,
            'query' => $query->toSql()
        ]);

        $request->url($url);

        $request->setCallbackFunction(function (CurlerRequest $request) {
            $handle = $request->getInfileHandle();
            if (is_resource($handle)) {
                fclose($handle);
            }
        });

        $request->setInfile($file_name);
        $this->_curler->addQueLoop($request);

        return new Statement($request);
    }

    /**
     * get Count Pending Query in Queue
     *
     * @return int
     */
    public function getCountPendingQueue(): int
    {
        return $this->_curler->countPending();
    }

    /**
     * set Connect TimeOut in seconds [CURLOPT_CONNECTTIMEOUT] ( int )
     *
     * @param float $connectTimeOut
     */
    public function setConnectTimeOut(float $connectTimeOut)
    {
        $this->_connectTimeOut = $connectTimeOut;
    }

    /**
     * get ConnectTimeOut in seconds
     *
     * @return float
     */
    public function getConnectTimeOut(): float
    {
        return $this->_connectTimeOut;
    }


    public function __findXClickHouseProgress($handle): bool
    {
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        // Search X-ClickHouse-Progress
        if ($code == 200) {
            $response = curl_multi_getcontent($handle);
            $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            if (!$header_size) {
                return false;
            }

            $header = substr($response, 0, $header_size);
            if (!$header) {
                return false;
            }

            $match = [];
            if (preg_match_all('/^X-ClickHouse-(?:Progress|Summary):(.*?)$/im', $header, $match)) {
                $data = @json_decode(end($match[1]), true);
                if ($data && is_callable($this->xClickHouseProgress)) {

                    if (is_array($this->xClickHouseProgress)) {
                        call_user_func_array($this->xClickHouseProgress, [$data]);
                    } else {
                        call_user_func($this->xClickHouseProgress, $data);
                    }

                }
            }
        }
        return false;
    }

    /**
     * @param Query $query
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return CurlerRequest
     * @throws \Exception
     */
    public function getRequestRead(Query $query, $whereInFile = null, $writeToFile = null): CurlerRequest
    {
        $urlParams = ['readonly' => 2];
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
            if (is_resource($fout)) {

                $isGz = $writeToFile->getGzip();

                if ($isGz) {
                    // write gzip header
                    // "\x1f\x8b\x08\x00\x00\x00\x00\x00"
                    // fwrite($fout, "\x1F\x8B\x08\x08".pack("V", time())."\0\xFF", 10);
                    // write the original file name
                    // $oname = str_replace("\0", "", basename($writeToFile->fetchFile()));
                    // fwrite($fout, $oname."\0", 1+strlen($oname));

                    fwrite($fout, "\x1f\x8b\x08\x00\x00\x00\x00\x00");

                }


                $request->setResultFileHandle($fout, $isGz)->setCallbackFunction(function (CurlerRequest $request) {
                    fclose($request->getResultFileHandle());
                });
            }
        }

        if ($this->stdErrOut) {
            $request->setStdErrOut($this->stdErrOut);
        }
        if ($this->xClickHouseProgress) {
            $request->setFunctionProgress([$this, '__findXClickHouseProgress']);
        }
        // ---------------------------------------------------------------------------------
        return $request;

    }

    public function cleanQueryDegeneration(): bool
    {
        $this->_query_degenerations = [];
        return true;
    }

    public function addQueryDegeneration(Degeneration $degeneration): bool
    {
        $this->_query_degenerations[] = $degeneration;
        return true;
    }

    /**
     * @param Query $query
     * @return CurlerRequest
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function getRequestWrite(Query $query): CurlerRequest
    {
        $urlParams = ['readonly' => 0];
        return $this->makeRequest($query, $urlParams);
    }

    /**
     * @throws TransportException
     */
    public function ping(): bool
    {
        $request = new CurlerRequest();
        $request->url($this->getUri())->verbose(false)->GET()->connectTimeOut($this->getConnectTimeOut());
        $this->_curler->execOne($request);

        return trim($request->response()->body()) === 'Ok.';
    }

    /**
     * @param string $sql
     * @param mixed[] $bindings
     * @return Query
     */
    private function prepareQuery($sql, $bindings): Query
    {

        // add Degeneration query
        foreach ($this->_query_degenerations as $degeneration) {
            $degeneration->bindParams($bindings);
        }

        return new Query($sql, $this->_query_degenerations);
    }


    /**
     * @param Query|string $sql
     * @param mixed[] $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return CurlerRequest
     * @throws \Exception
     */
    private function prepareSelect($sql, $bindings, $whereInFile, $writeToFile = null): CurlerRequest
    {
        if ($sql instanceof Query) {
            return $this->getRequestWrite($sql);
        }
        $query = $this->prepareQuery($sql, $bindings);
        $query->setFormat('JSON');
        return $this->getRequestRead($query, $whereInFile, $writeToFile);
    }


    /**
     * @param Query|string $sql
     * @param mixed[] $bindings
     * @return CurlerRequest
     * @throws \ClickHouseDB\Exception\TransportException
     */
    private function prepareWrite($sql, $bindings = []): CurlerRequest
    {
        if ($sql instanceof Query) {
            return $this->getRequestWrite($sql);
        }

        $query = $this->prepareQuery($sql, $bindings);

        if (strpos($sql, 'ON CLUSTER') === false) {
            return $this->getRequestWrite($query);
        }

        if (strpos($sql, 'CREATE') === 0 || strpos($sql, 'DROP') === 0 || strpos($sql, 'ALTER') === 0) {
            $query->setFormat('JSON');
        }

        return $this->getRequestWrite($query);
    }

    /**
     * @return bool
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function executeAsync(): bool
    {
        return $this->_curler->execLoopWait();
    }

    /**
     * @param Query|string $sql
     * @param mixed[] $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     * @throws \Exception
     */
    public function select($sql, array $bindings = [], $whereInFile = null, $writeToFile = null): Statement
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile, $writeToFile);
        $this->_curler->execOne($request);
        return new Statement($request);
    }

    /**
     * @param Query|string $sql
     * @param mixed[] $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     * @throws \Exception
     */
    public function selectAsync($sql, array $bindings = [], $whereInFile = null, $writeToFile = null): Statement
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile, $writeToFile);
        $this->_curler->addQueLoop($request);
        return new Statement($request);
    }

    /**
     * @param callable $callback
     */
    public function setProgressFunction(callable $callback) : void
    {
        $this->xClickHouseProgress = $callback;
    }

    /**
     * @param string $sql
     * @param mixed[] $bindings
     * @param bool $exception
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function write($sql, array $bindings = [], $exception = true): Statement
    {
        $request = $this->prepareWrite($sql, $bindings);
        $this->_curler->execOne($request);
        $response = new Statement($request);
        if ($exception) {
            if ($response->isError()) {
                $response->error();
            }
        }
        return $response;
    }

    /**
     * @param Stream $streamRW
     * @param CurlerRequest $request
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    private function streaming(Stream $streamRW, CurlerRequest $request): Statement
    {
        $callable = $streamRW->getClosure();
        $stream = $streamRW->getStream();


        try {


            if (!is_callable($callable)) {
                if ($streamRW->isWrite()) {

                    $callable = function ($ch, $fd, $length) use ($stream) {
                        return ($line = fread($stream, $length)) ? $line : '';
                    };
                } else {
                    $callable = function ($ch, $fd) use ($stream) {
                        return fwrite($stream, $fd);
                    };
                }
            }

            if ($streamRW->isGzipHeader()) {

                if ($streamRW->isWrite()) {
                    $request->header('Content-Encoding', 'gzip');
                    $request->header('Content-Type', 'application/x-www-form-urlencoded');
                } else {
                    $request->header('Accept-Encoding', 'gzip');
                }

            }


            $request->header('Transfer-Encoding', 'chunked');

            if ($streamRW->isWrite()) {
                $request->setReadFunction($callable);
            } else {
                $request->setWriteFunction($callable);

//                $request->setHeaderFunction($callableHead);
            }


            $this->_curler->execOne($request, true);
            $response = new Statement($request);
            if ($response->isError()) {
                $response->error();
            }
            return $response;
        } finally {
            if ($streamRW->isWrite())
                fclose($stream);
        }


    }


    /**
     * @param Stream $streamRead
     * @param string $sql
     * @param mixed[] $bindings
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function streamRead(Stream $streamRead, $sql, $bindings = []): Statement
    {
        $sql = $this->prepareQuery($sql, $bindings);
        $request = $this->getRequestRead($sql);
        return $this->streaming($streamRead, $request);

    }

    /**
     * @param Stream $streamWrite
     * @param string $sql
     * @param mixed[] $bindings
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function streamWrite(Stream $streamWrite, $sql, $bindings = []): Statement
    {
        $sql = $this->prepareQuery($sql, $bindings);
        $request = $this->writeStreamData($sql);
        return $this->streaming($streamWrite, $request);
    }
}
