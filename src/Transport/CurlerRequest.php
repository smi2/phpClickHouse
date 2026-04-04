<?php

namespace ClickHouseDB\Transport;

use const CURLOPT_HTTPGET;
use const CURLOPT_POST;

class CurlerRequest
{
    /**
     * @var array
     */
    public array $extendinfo = [];

    /**
     * @var string|array
     */
    private string|array $parameters = '';

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var array
     */
    private array $headers = []; // Parsed reponse header object.

    /**
     * @var string
     */
    private string $url = '';

    /**
     * @var string
     */
    private string $method = '';

    /**
     * @var mixed
     */
    private mixed $id = false;

    /**
     * @var \CurlHandle|null
     */
    private \CurlHandle|null $handle = null;

    /** @var CurlerResponse|null */
    private ?CurlerResponse $response = null;

    /** @var bool */
    private bool $_persistent = false;

    /**
     * @var bool
     */
    private bool $_attachFiles = false;

    /**
     * @var mixed
     */
    private mixed $callback_class = '';

    /**
     * @var string
     */
    private string $callback_functionName = '';

    /**
     * @var bool
     */
    private bool $_httpCompression = false;

    /**
     * @var mixed
     */
    private mixed $callback_function = null;

    /**
     * @var mixed
     */
    private mixed $infile_handle = false;

    /**
     * @var int
     */
    private int $_dns_cache = 120;

    /**
     * @var mixed
     */
    private mixed $resultFileHandle = null;

    /**
     * @var string|null
     */
    private ?string $sslCa = null;


    /**
     * @var mixed
     */
    private mixed $stdErrOut = null;
    /**
     * @param mixed $id
     */
    public function __construct(mixed $id = false)
    {
        $this->id = $id;

        $this->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->header('Expires', '0');
        $this->header('Pragma', 'no-cache');

        $this->options = array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5, // Количество секунд ожидания при попытке соединения
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HEADER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_AUTOREFERER => 1, // при редиректе подставлять в «Referer:» значение из «Location:»
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => 'smi2/PHPClickHouse/client',
        );
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->close();
    }


    public function close(): void
    {
        $this->handle = null;
    }

    /**
     * @param array $attachFiles
     */
    public function attachFiles(array $attachFiles): void
    {
        $this->header("Content-Type", "multipart/form-data");

        $out = [];
        foreach ($attachFiles as $post_name => $file_path) {
            $out[$post_name] = new \CURLFile($file_path);
        }

        $this->_attachFiles = true;
        $this->parameters($out);
    }


    /**
     * @param bool $set
     * @return static
     */
    public function id($set = false): static
    {
        if ($set) {
            $this->id = $set;
        }

        return $this;
    }

    /**
     * @param array $params
     * @return static
     */
    public function setRequestExtendedInfo($params): static
    {
        $this->extendinfo = $params;
        return $this;
    }

    /**
     * @param string|integer|null $key
     * @return mixed
     */
    public function getRequestExtendedInfo($key = null): mixed
    {
        if ($key) {
            return isset($this->extendinfo[$key]) ? $this->extendinfo[$key] : false;
        }

        return $this->extendinfo;
    }

    /**
     * @return mixed
     */
    public function getInfileHandle(): mixed
    {
        return $this->infile_handle;
    }

    /**
     * @param string $file_name
     * @return mixed
     */
    public function setInfile($file_name): mixed
    {
        $this->header('Expect', '');
        $this->infile_handle = fopen($file_name, 'r');
        if (is_resource($this->infile_handle)) {

            if ($this->_httpCompression) {
                $this->header('Content-Encoding', 'gzip');
                $this->header('Content-Type', 'application/x-www-form-urlencoded');

                stream_filter_append($this->infile_handle, 'zlib.deflate', STREAM_FILTER_READ, ["window" => 30]);

                $this->options[CURLOPT_SAFE_UPLOAD] = 1;
            } else {
                $this->options[CURLOPT_INFILESIZE] = filesize($file_name);
            }

            $this->options[CURLOPT_INFILE] = $this->infile_handle;
        }

        return $this->infile_handle;
    }

    /**
     * @param callable $callback
     */
    public function setCallbackFunction(callable $callback): void
    {
        $this->callback_function = $callback;
    }

    /**
     * @param callable $callback
     */
    public function setWriteFunction(callable $callback): void
    {
        $this->options[CURLOPT_WRITEFUNCTION] = $callback;
    }

    /**
     * @param callable $callback
     */
    public function setReadFunction(callable $callback): void
    {
        $this->options[CURLOPT_READFUNCTION] = $callback;
    }

    public function setHeaderFunction(callable $callback): void
    {
        $this->options[CURLOPT_HEADERFUNCTION] = $callback;
    }

    /**
     * @param string $classCallBack
     * @param string $functionName
     */
    public function setCallback($classCallBack, $functionName): void
    {
        $this->callback_class = $classCallBack;
        $this->callback_functionName = $functionName;
    }

    /**
     *
     */
    public function onCallback(): void
    {
        if ($this->callback_function) {
            $x = $this->callback_function;
            $x($this);
        }

        if ($this->callback_class && $this->callback_functionName) {
            $c = $this->callback_functionName;
            $this->callback_class->$c($this);
        }
    }

    public function getDetails(): array
    {
        return [
            'url'        => $this->url,
            'method'     => $this->method,
            'parameters' => $this->parameters,
            'headers'    => $this->headers,
        ];
    }

    /**
     * @param mixed $stream
     * @return void
     */
    public function setStdErrOut(mixed $stream): void
    {
        if (is_resource($stream)) {
            $this->stdErrOut=$stream;
        }

    }

    /**
     * @param bool $result
     * @return string
     */
    public function dump(bool $result = false): string
    {
        $message = "\n------------  Request ------------\n";
        $message .= 'URL:' . $this->url . "\n\n";
        $message .= 'METHOD:' . $this->method . "\n\n";
        $message .= 'PARAMS:' . print_r($this->parameters, true) . "\n";
        $message .= 'PARAMS:' . print_r($this->headers, true) . "\n";
        $message .= "-----------------------------------\n";

        if ($result) {
            return $message;
        }

        echo $message;
        return '';
    }

    /**
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->id;
    }

    /**
     * @param integer $key
     * @param mixed $value
     * @return static
     */
    public function option($key, $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @return static
     */
    public function persistent(): static
    {
        $this->_persistent = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPersistent(): bool
    {
        return $this->_persistent;
    }

    /**
     * @param int $sec
     * @return static
     */
    public function keepAlive(int $sec = 60): static
    {
        $this->options[CURLOPT_FORBID_REUSE] = TRUE;
        $this->headers['Connection'] = 'Keep-Alive';
        $this->headers['Keep-Alive'] = $sec;

        return $this;
    }

    /**
     * @param bool $flag
     * @return static
     */
    public function verbose(bool $flag = true): static
    {
        $this->options[CURLOPT_VERBOSE] = $flag;
        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return static
     */
    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders():array
    {
        $head = [];
        foreach ($this->headers as $key => $value) {
            $head[] = sprintf("%s: %s", $key, $value);
        }
        return $head;
    }

    /**
     * @param string $url
     * @return static
     */
    public function url(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl():string
    {
        return $this->url;
    }


    /**
     * @param string $id
     * @return string
     */
    public function getUniqHash(string $id):string
    {
        return $id . '.' . microtime() . mt_rand(0, 1000000);
    }

    /**
     * @param bool $flag
     */
    public function httpCompression(bool $flag):void
    {
        if ($flag) {
            $this->_httpCompression = $flag;
            $this->options[CURLOPT_ENCODING] = 'gzip';
        } else {
            $this->_httpCompression = false;
            unset($this->options[CURLOPT_ENCODING]);
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @return static
     */
    public function authByBasicAuth($username, $password): static
    {
        $this->options[CURLOPT_USERPWD] = sprintf("%s:%s", $username, $password);
        return $this;
    }

    public function authByHeaders($username, $password): static
    {
        $this->headers['X-ClickHouse-User'] = $username;
        $this->headers['X-ClickHouse-Key'] = $password;
        return $this;
    }

    /**
     * @param array|string $data
     * @return static
     */
    public function parameters($data): static
    {
        $this->parameters = $data;
        return $this;
    }

    /**
     * The number of seconds to wait when trying to connect. Use 0 for infinite waiting.
     *
     * @param float $seconds
     * @return static
     */
    public function connectTimeOut(float $seconds = 1.0): static
    {
        $this->options[CURLOPT_CONNECTTIMEOUT_MS] = (int) ($seconds*1000.0);
        return $this;
    }

    /**
     * The maximum number of seconds (float) allowed to execute cURL functions.
     *
     * @param float $seconds
     * @return static
     */
    public function timeOut(float $seconds = 10): static
    {
        return $this->timeOutMs((int) ($seconds * 1000.0));
    }

    /**
     * The maximum allowed number of milliseconds to perform cURL functions.
     *
     * @param int $ms millisecond
     * @return static
     */
    protected function timeOutMs(int $ms = 10000): static
    {
        $this->options[CURLOPT_TIMEOUT_MS] = $ms;
        return $this;
    }


    /**
     * @param array|mixed $data
     * @return static
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function parameters_json($data): static
    {

        $this->header("Content-Type", "application/json, text/javascript; charset=utf-8");
        $this->header("Accept", "application/json, text/javascript, */*; q=0.01");

        if ($data === null) {
            $this->parameters = '{}';
            return $this;
        }

        if (is_string($data)) {
            $this->parameters = $data;
            return $this;
        }

        $this->parameters = json_encode($data);

        if (!$this->parameters && $data) {
            throw new \ClickHouseDB\Exception\TransportException('Cant json_encode: ' . strval($data));
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResultFileHandle(): mixed
    {
        return $this->resultFileHandle;
    }

    /**
     * @return bool
     */
    public function isResultFile(): bool
    {
        return ($this->resultFileHandle ? true : false);
    }

    /**
     * @param mixed $h resource
     * @param bool $zlib
     * @return static
     */
    public function setResultFileHandle($h, $zlib = false): static
    {
        $this->resultFileHandle = $h;
        if ($zlib) {
            $params = array('level' => 6, 'window' => 15, 'memory' => 9);
            stream_filter_append($this->resultFileHandle, 'zlib.deflate', STREAM_FILTER_WRITE, $params);
        }
        return $this;
    }

    /**
     * @return static
     */
    public function PUT(): static
    {
        return $this->execute('PUT');
    }

    /**
     * @return static
     */
    public function POST(): static
    {
        return $this->execute('POST');
    }

    /**
     * @return static
     */
    public function OPTIONS(): static
    {
        return $this->execute('OPTIONS');
    }

    /**
     * @return static
     */
    public function GET(): static
    {
        return $this->execute('GET');
    }

    /**
     * The number of seconds that DNS records are stored in memory. By default this parameter is 120 (2 minutes).
     *
     * @param integer $set
     * @return static
     */
    public function setDnsCache($set): static
    {
        $this->_dns_cache = $set;
        return $this;
    }

    /**
     * The number of seconds that DNS records are stored in memory. By default this parameter is 120 (2 minutes).
     *
     * @return int
     */
    public function getDnsCache(): int
    {
        return $this->_dns_cache;
    }

    /**
     * Sets client certificate
     *
     * @param string $filePath
     */
    public function setSslCa($filePath): void
    {
        $this->option(CURLOPT_SSL_VERIFYPEER, true);
        $this->option(CURLOPT_CAINFO, $filePath);
    }

    /**
     * @param string $method
     * @return static
     */
    private function execute($method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return CurlerResponse
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function response(): CurlerResponse
    {
        if (!$this->response) {
            throw new \ClickHouseDB\Exception\TransportException('Can`t fetch response - is empty');
        }

        return $this->response;
    }

    public function isResponseExists(): bool
    {
        return $this->response !== null;
    }

    public function setResponse(CurlerResponse $response): void
    {
        $this->response = $response;
    }

    /**
     * @return mixed
     */
    public function handle(): mixed
    {
        $this->prepareRequest();
        return $this->handle;
    }

    /**
     * @param callable $callback
     * @throws \Exception
     */
    public function setFunctionProgress(callable $callback): void
    {
        if (!is_callable($callback)) {
            throw new \Exception('setFunctionProgress not is_callable');
        }

        $this->option(CURLOPT_NOPROGRESS, false);
        $this->option(CURLOPT_PROGRESSFUNCTION, $callback); // version 5.5.0
    }


    /**
     * @return bool
     */
    private function prepareRequest(): bool
    {
        if (!$this->handle) {
            $this->handle = curl_init();
        }

        $curl_opt = $this->options;
        $method = $this->method;

        if ($this->_attachFiles) {
            $curl_opt[CURLOPT_SAFE_UPLOAD] = true;
        }


        if (strtoupper($method) == 'GET') {
            $curl_opt[CURLOPT_HTTPGET] = true;
            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curl_opt[CURLOPT_POSTFIELDS] = false;
        } else {
            if (strtoupper($method) === 'POST') {
                $curl_opt[CURLOPT_POST] = true;
            }

            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

            if ($this->parameters) {
                $curl_opt[CURLOPT_POSTFIELDS] = $this->parameters;

                if (!is_array($this->parameters)) {
                    $this->header('Content-Length',  mb_strlen($this->parameters, '8bit'));
                }
            }
        }
        // CURLOPT_DNS_CACHE_TIMEOUT - Количество секунд, в течение которых в памяти хранятся DNS-записи.
        $curl_opt[CURLOPT_DNS_CACHE_TIMEOUT] = $this->getDnsCache();
        $curl_opt[CURLOPT_URL] = $this->url;

        if (!empty($this->headers) && sizeof($this->headers)) {
            $curl_opt[CURLOPT_HTTPHEADER] = [];

            foreach ($this->headers as $key => $value) {
                $curl_opt[CURLOPT_HTTPHEADER][] = sprintf("%s: %s", $key, $value);
            }
        }

        if (!empty($curl_opt[CURLOPT_INFILE])) {

            $curl_opt[CURLOPT_PUT] = true;
        }

        if (!empty($curl_opt[CURLOPT_WRITEFUNCTION])) {
            $curl_opt[CURLOPT_HEADER] = false;
        }

        if ($this->resultFileHandle) {
            $curl_opt[CURLOPT_FILE] = $this->resultFileHandle;
            $curl_opt[CURLOPT_HEADER] = false;
        }

        if ($this->options[CURLOPT_VERBOSE]) {
            $msg="\n-----------BODY REQUEST----------\n" . $curl_opt[CURLOPT_POSTFIELDS] . "\n------END--------\n";
            if ($this->stdErrOut && is_resource($this->stdErrOut)) {
                fwrite($this->stdErrOut,$msg);
            } else {
                echo $msg;
            }
        }

        if ($this->stdErrOut) {
            if (is_resource($this->stdErrOut)) {
                $curl_opt[CURLOPT_STDERR]=$this->stdErrOut;
            }
        }

        curl_setopt_array($this->handle, $curl_opt);
        return true;
    }
}
