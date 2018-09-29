<?php

namespace ClickHouseDB\Transport;

use ClickHouseDB\Exception\TransportException;
use ClickHouseDB\Query\Degeneration;
use ClickHouseDB\Query\Query;
use ClickHouseDB\Query\WhereInFile;
use ClickHouseDB\Query\WriteToFile;
use ClickHouseDB\Settings;
use ClickHouseDB\Statement;
use function array_merge;
use function fclose;
use function http_build_query;
use function is_callable;
use function is_resource;
use const PHP_EOL;
use function stripos;

class Http
{
    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /** @var bool */
    private $https;

    /**
     * @var bool|int
     */
    private $verbose = false;

    /**
     * @var CurlerRolling
     */
    private $_curler = null;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var array
     */
    private $_query_degenerations = [];

    /**
     * Count seconds (float)
     *
     * @var float
     */
    private $timeout = 20;

    /**
     * Count seconds (float)
     *
     * @var float
     */
    private $connectTimeout = 5;

    /**
     * @var callable|null
     */
    private $xClickHouseProgress;

    /** @var string */
    private $database;

    public function __construct(
        string $host,
        int $port,
        string $username,
        string $password,
        string $database,
        bool $https = false
    ) {
        $this->host     = $host;
        $this->port     = $port;
        $this->username = $username;
        $this->password = $password;
        $this->setDatabase($database);
        $this->setHttps($https);
        $this->settings = new Settings();

        $this->setCurler();
    }

    public function setHttps(bool $https) : void
    {
        $this->https = $https;
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

    public function setDatabase(string $database) : void
    {
        $this->database = $database;
    }

    public function setHost(string $host) : void
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        $proto = 'http';
        if ($this->https) {
            $proto = 'https';
        }
        $uri = $proto . '://' . $this->host;
        if (stripos($this->host, '/') !== false || stripos($this->host, ':') !== false) {
            return $uri;
        }
        if ($this->port > 0) {
            return $uri . ':' . $this->port;
        }

        return $uri;
    }

    public function getSettings() : Settings
    {
        return $this->settings;
    }

    public function setVerbose(bool $flag) : void
    {
        $this->verbose = $flag;
    }

    /**
     * @param mixed[] $params
     */
    private function getUrl(array $params = []) : string
    {
        $settings = $this->getSettings()->getQueryableSettings($params);

        return $this->getUri() . '?' . http_build_query(array_merge(['database' => $this->database], $settings));
    }

    /**
     * @param array $extendinfo
     * @return CurlerRequest
     */
    private function newRequest($extendinfo)
    {
        $new = new CurlerRequest();
        $new->auth($this->username, $this->password)
            ->POST()
            ->setRequestExtendedInfo($extendinfo);

        if ($this->getSettings()->isHttpCompressionEnabled()) {
            $new->httpCompression(true);
        }

        if ($this->getSettings()->getSessionId()) {
            $new->persistent();
        }

        $new->setTimeout($this->timeout);
        $new->setConnectTimeout($this->connectTimeout); // one sec
        $new->keepAlive(); // one sec
        $new->verbose((bool) $this->verbose);

        return $new;
    }

    /**
     * @param array $urlParams
     * @param bool  $query_as_string
     * @return CurlerRequest
     * @throws \ClickHouseDB\Exception\TransportException
     */
    private function makeRequest(Query $query, array $urlParams = [], $query_as_string = false)
    {
        $sql = $query->toSql();

        if ($query_as_string) {
            $urlParams['query'] = $sql;
        }

        $url = $this->getUrl($urlParams);

        $extendinfo = [
            'sql'    => $sql,
            'query'  => $query,
            'format' => $query->getFormat(),
        ];

        $new = $this->newRequest($extendinfo);
        $new->url($url);

        if (! $query_as_string) {
            $new->parameters_json($sql);
        }
        if ($this->getSettings()->isHttpCompressionEnabled()) {
            $new->httpCompression(true);
        }

        return $new;
    }

    /**
     * @param string|Query $sql
     * @return CurlerRequest
     */
    public function writeStreamData($sql)
    {

        if ($sql instanceof Query) {
            $query = $sql;
        } else {
            $query = new Query($sql);
        }

        $url        = $this->getUrl([
            'query' => $query->toSql(),
        ]);
        $extendinfo = [
            'sql'    => $sql,
            'query'  => $query,
            'format' => $query->getFormat(),
        ];

        $request = $this->newRequest($extendinfo);
        $request->url($url);

        return $request;
    }

    /**
     * @param string $sql
     * @param string $file_name
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function writeAsyncCSV($sql, $file_name)
    {
        $query = new Query($sql);

        $url = $this->getUrl([
            'query' => $query->toSql(),
        ]);

        $extendinfo = [
            'sql'    => $sql,
            'query'  => $query,
            'format' => $query->getFormat(),
        ];

        $request = $this->newRequest($extendinfo);
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
    public function getCountPendingQueue()
    {
        return $this->_curler->countPending();
    }

    public function getTimeout() : float
    {
        return $this->timeout;
    }

    public function setTimeout(float $timeout) : void
    {
        $this->timeout = $timeout;
    }

    /**
     * Get ConnectTimeout in seconds
     */
    public function getConnectTimeout() : float
    {
        return $this->connectTimeout;
    }

    /**
     * Set Connect Timeout in seconds
     */
    public function setConnectTimeout(float $connectTimeOut) : void
    {
        $this->connectTimeout = $connectTimeOut;
    }

    private function findXClickHouseProgress($handle)
    {
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        // Search X-ClickHouse-Progress
        if ($code == 200) {
            $response    = curl_multi_getcontent($handle);
            $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            if (! $header_size) {
                return false;
            }

            $header = substr($response, 0, $header_size);
            if (! $header_size) {
                return false;
            }
            $pos = strrpos($header, 'X-ClickHouse-Progress');

            if (! $pos) {
                return false;
            }

            $last = substr($header, $pos);
            $data = @json_decode(str_ireplace('X-ClickHouse-Progress:', '', $last), true);

            if ($data && $this->xClickHouseProgress !== null) {
                ($this->xClickHouseProgress)($data);
            }
        }
    }

    /**
     * @param Query            $query
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return CurlerRequest
     * @throws \Exception
     */
    public function getRequestRead(Query $query, $whereInFile = null, $writeToFile = null)
    {
        $query_as_string = false;
        $urlParams       = [];
        // ---------------------------------------------------------------------------------
        if ($whereInFile instanceof WhereInFile && $whereInFile->size()) {
            // $request = $this->prepareSelectWhereIn($request, $whereInFile);
            $structure       = $whereInFile->fetchUrlParams();
            $urlParams       = $structure;
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
        if ($this->xClickHouseProgress !== null) {
            $request->setFunctionProgress(function ($x) {
                return $this->findXClickHouseProgress($x);
            });
        }

        // ---------------------------------------------------------------------------------
        return $request;
    }

    public function cleanQueryDegeneration() : void
    {
        $this->_query_degenerations = [];
    }

    public function addQueryDegeneration(Degeneration $degeneration) : void
    {
        $this->_query_degenerations[] = $degeneration;
    }

    /**
     * @param Query $query
     * @return CurlerRequest
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function getRequestWrite(Query $query)
    {
        return $this->makeRequest($query);
    }

    /**
     * @throws TransportException
     */
    public function ping() : bool
    {
        $request = new CurlerRequest();
        $request->url($this->getUri())->verbose(false)->GET()->setConnectTimeout($this->getConnectTimeout());
        $this->_curler->execOne($request);

        return $request->response()->body() === 'Ok.' . PHP_EOL;
    }

    /**
     * @param string  $sql
     * @param mixed[] $bindings
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
     * @param Query|string     $sql
     * @param mixed[]          $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return CurlerRequest
     * @throws \Exception
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
     * @param Query|string $sql
     * @param mixed[]      $bindings
     * @return CurlerRequest
     * @throws \ClickHouseDB\Exception\TransportException
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
     * @return bool
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function executeAsync()
    {
        return $this->_curler->execLoopWait();
    }

    /**
     * @param Query|string     $sql
     * @param mixed[]          $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     * @throws \Exception
     */
    public function select($sql, array $bindings = [], $whereInFile = null, $writeToFile = null)
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile, $writeToFile);
        $this->_curler->execOne($request);

        return new Statement($request);
    }

    /**
     * @param Query|string     $sql
     * @param mixed[]          $bindings
     * @param null|WhereInFile $whereInFile
     * @param null|WriteToFile $writeToFile
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     * @throws \Exception
     */
    public function selectAsync($sql, array $bindings = [], $whereInFile = null, $writeToFile = null)
    {
        $request = $this->prepareSelect($sql, $bindings, $whereInFile, $writeToFile);
        $this->_curler->addQueLoop($request);

        return new Statement($request);
    }

    public function setProgressFunction(callable $callback) : void
    {
        $this->xClickHouseProgress = $callback;
    }

    /**
     * @param string  $sql
     * @param mixed[] $bindings
     * @param bool    $exception
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function write($sql, array $bindings = [], $exception = true)
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
     * @param Stream        $streamRW
     * @param CurlerRequest $request
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    private function streaming(Stream $streamRW, CurlerRequest $request)
    {
        $callable = $streamRW->getClosure();
        $stream   = $streamRW->getStream();

        try {

            if (! is_callable($callable)) {
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
     * @param Stream  $streamRead
     * @param string  $sql
     * @param mixed[] $bindings
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function streamRead(Stream $streamRead, $sql, $bindings = [])
    {
        $sql     = $this->prepareQuery($sql, $bindings);
        $request = $this->getRequestRead($sql);

        return $this->streaming($streamRead, $request);
    }

    /**
     * @param Stream  $streamWrite
     * @param string  $sql
     * @param mixed[] $bindings
     * @return Statement
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function streamWrite(Stream $streamWrite, $sql, $bindings = [])
    {
        $sql     = $this->prepareQuery($sql, $bindings);
        $request = $this->writeStreamData($sql);

        return $this->streaming($streamWrite, $request);
    }
}
