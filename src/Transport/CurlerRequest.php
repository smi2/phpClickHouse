<?php

namespace Curler;

/**
 * Class Request
 * @package Curler
 */
class Request
{
    /**
     * @var array
     */
    public $extendinfo = array();

    /**
     * @var string
     */
    private $parameters = '';

    /**
     * @var array
     */
    private $options;

    /**
     * @var
     */
    private $headers; // Parsed reponse header object.

    /**
     * @var
     */
    private $url;

    /**
     * @var
     */
    private $method;

    /**
     * @var bool
     */
    private $id;

    /**
     * @var
     */
    private $handle;

    /**
     * @var
     */
    private $resp;

    /**
     * @var bool
     */
    private $_persistent = false;

    /**
     * @var bool
     */
    private $_attachFiles = false;

    /**
     * @var string
     */
    private $callback_class = '';

    /**
     * @var string
     */
    private $callback_functionName = '';

    /**
     * @var bool
     */
    private $_httpCompression = false;

    /**
     * @var
     */
    private $callback_function;

    /**
     * @var bool
     */
    private $infile_handle = false;

    /**
     * @var int
     */
    private $_dns_cache = 120;

    /**
     * @var resource
     */
    private $resultFileHandle = null;

    /**
     * Request constructor.
     * @param bool $id
     */
    public function __construct($id = false)
    {
        $this->id = $id;

        $this->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->header('Expires', '0');
        $this->header('Pragma', 'no-cache');

        $this->options = array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,    // Количество секунд ожидания при попытке соединения
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HEADER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_AUTOREFERER => 1,       // при редиректе подставлять в «Referer:» значение из «Location:»
            CURLOPT_BINARYTRANSFER => 1,    // передавать в binary-safe
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => 'smi2/PHPClickHouse/client',
        );
    }

    /**
     *
     */
    public function __destructor()
    {
        $this->close();
    }


    /**
     *
     */
    public function close()
    {
        curl_close($this->handle);
        $this->handle = null;
    }

    /**
     * @param $attachFiles
     */
    public function attachFiles($attachFiles)
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
     * @return $this
     */
    public function id($set = false)
    {
        if ($set) {
            $this->id = $set;
        }

        return $this;
    }

    /**
     * @param $params
     * @return $this
     */
    public function setRequestExtendedInfo($params)
    {
        $this->extendinfo = $params;
        return $this;
    }

    /**
     * @param null $key
     * @return mixed
     */
    public function getRequestExtendedInfo($key = null)
    {
        if ($key) {
            return isset($this->extendinfo[$key]) ? $this->extendinfo[$key] : false;
        }

        return $this->extendinfo;
    }

    /**
     * @return bool
     */
    public function getInfileHandle()
    {
        return $this->infile_handle;
    }

    /**
     * @param $file_name
     * @return bool
     */
    public function setInfile($file_name)
    {
        $this->header('Expect', '');
        $this->infile_handle = fopen($file_name, 'r');

        if ($this->_httpCompression) {
            $this->header('Content-Encoding', 'gzip');
            $this->header('Content-Type', 'application/x-www-form-urlencoded');

            stream_filter_append($this->infile_handle, 'zlib.deflate', STREAM_FILTER_READ, ["window" => 30]);

            $this->options[CURLOPT_SAFE_UPLOAD] = 1;
        } else {
            $this->options[CURLOPT_INFILESIZE] = filesize($file_name);
        }

        $this->options[CURLOPT_INFILE] = $this->infile_handle;

        return $this->infile_handle;
    }

    /**
     * @param $callback
     */
    public function setCallbackFunction($callback)
    {
        $this->callback_function = $callback;
    }

    /**
     * @param $classCallBack
     * @param $functionName
     */
    public function setCallback($classCallBack, $functionName)
    {
        $this->callback_class = $classCallBack;
        $this->callback_functionName = $functionName;
    }

    /**
     *
     */
    public function onCallback()
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

    /**
     * @param bool $result
     * @return string
     */
    public function dump($result = false)
    {
        $message = "\n------------  Request ------------\n";
        $message .= 'URL:' . $this->url . "\n\n";
        $message .= 'METHOD:' . $this->method . "\n\n";
        $message .= 'PARAMS:' . print_r($this->parameters, true) . "\n";
        $message .= "-----------------------------------\n";

        if ($result) {
            return $message;
        }

        echo $message;
    }

    /**
     * @return bool
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    private function option($key, $value)
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function persistent()
    {
        $this->_persistent = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPersistent()
    {
        return $this->_persistent;
    }

    /**
     * @param int $sec
     * @return $this
     */
    public function keepAlive($sec = 60)
    {
        $this->options[CURLOPT_FORBID_REUSE] = TRUE;
        $this->headers['Connection'] = 'Keep-Alive';
        $this->headers['Keep-Alive'] = $sec;

        return $this;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function verbose($flag = true)
    {
        $this->options[CURLOPT_VERBOSE] = $flag;
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function header($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }


    /**
     * @param $url
     * @return $this
     */
    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }


    /**
     * @param $id
     * @return string
     */
    public function getUniqHash($id)
    {
        return $id . '.' . microtime() . mt_rand(0, 1000000);
    }

    /**
     * @param $flag
     */
    public function httpCompression($flag)
    {
        if ($flag) {
            $this->_httpCompression = $flag;
            $this->options[CURLOPT_ENCODING] = 'gzip';
        }
    }

    /**
     * @param $username
     * @param $password
     * @return $this
     */
    public function auth($username, $password)
    {
        $this->options[CURLOPT_USERPWD] = sprintf("%s:%s", $username, $password);
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function parameters($data)
    {
        $this->parameters = $data;
        return $this;
    }

    /**
     * Количество секунд ожидания при попытке соединения. Используйте 0 для бесконечного ожидания.
     *
     * @param int $seconds
     * @return $this
     */
    public function connectTimeOut($seconds = 1)
    {
        $this->options[CURLOPT_CONNECTTIMEOUT] = $seconds;
        return $this;
    }

    /**
     * Максимально позволенное количество секунд для выполнения cURL-функций.
     *
     * @param int $seconds
     * @return $this
     */
    public function timeOut($seconds = 10)
    {
        return $this->timeOutMs($seconds * 1000);
    }

    /**
     * Максимально позволенное количество миллисекунд для выполнения cURL-функций.
     *
     * @param int $ms
     * @return $this
     */
    protected function timeOutMs($ms = 10)
    {
        $this->options[CURLOPT_TIMEOUT_MS] = $ms;
        return $this;
    }


    /**
     * @param $data
     * @return $this
     * @throws \ClickHouseDB\TransportException
     */
    public function parameters_json($data)
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
            throw new \ClickHouseDB\TransportException('Cant json_encode: ' . $data);
        }

        return $this;
    }

    /**
     * @return resource
     */
    public function getResultFileHandle()
    {
        return $this->resultFileHandle;
    }

    /**
     * @return bool
     */
    public function isResultFile()
    {
        return ($this->resultFileHandle ? true : false);
    }

    /**
     * @param $h resource
     * @return $this
     */
    public function setResultFileHandle($h, $zlib = false)
    {
        $this->resultFileHandle = $h;
        if ($zlib) {
            $params = array('level' => 6, 'window' => 15, 'memory' => 9);
            stream_filter_append($this->resultFileHandle, 'zlib.deflate', STREAM_FILTER_WRITE, $params);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function PUT()
    {
        return $this->execute('PUT');
    }

    /**
     * @return $this
     */
    public function POST()
    {
        return $this->execute('POST');
    }

    /**
     * @return $this
     */
    public function OPTIONS()
    {
        return $this->execute('OPTIONS');
    }

    /**
     * @return $this
     */
    public function GET()
    {
        return $this->execute('GET');
    }

    /**
     * Количество секунд, в течение которых в памяти хранятся DNS-записи. По умолчанию этот параметр равен 120 (2 минуты).
     *
     * @param $set
     * @return $this
     */
    public function setDnsCache($set)
    {
        $this->_dns_cache = $set;
        return $this;
    }

    /**
     * Количество секунд, в течение которых в памяти хранятся DNS-записи. По умолчанию этот параметр равен 120 (2 минуты).
     *
     * @return int
     */
    public function getDnsCache()
    {
        return $this->_dns_cache;
    }

    /**
     * @param $method
     * @return $this
     */
    private function execute($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return \Curler\Response
     * @throws \ClickHouseDB\TransportException
     */
    public function response()
    {
        if (!$this->resp) {
            throw new \ClickHouseDB\TransportException('Can`t fetch response - is empty');
        }

        return $this->resp;
    }

    /**
     * @return bool
     */
    public function isResponseExists()
    {
        return ($this->resp ? true : false);
    }

    /**
     * @param Response $resp
     */
    public function setResponse(\Curler\Response $resp)
    {
        $this->resp = $resp;
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        $this->prepareRequest();
        return $this->handle;
    }

    /**
     * @return bool
     */
    private function prepareRequest()
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
            $curl_opt[CURLOPT_HTTPGET] = TRUE;
            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curl_opt[CURLOPT_POSTFIELDS] = false;
        } else {
            if (strtoupper($method) === 'POST') {
                $curl_opt[CURLOPT_POST] = TRUE;
            }

            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

            if ($this->parameters) {
                $curl_opt[CURLOPT_POSTFIELDS] = $this->parameters;

                if (!is_array($this->parameters)) {
                    $this->header('Content-Length', strlen($this->parameters));
                }
            }
        }
        // CURLOPT_DNS_CACHE_TIMEOUT - Количество секунд, в течение которых в памяти хранятся DNS-записи.
        $curl_opt[CURLOPT_DNS_CACHE_TIMEOUT] = $this->getDnsCache();
        $curl_opt[CURLOPT_URL] = $this->url;

        if ($this->headers && sizeof($this->headers)) {
            $curl_opt[CURLOPT_HTTPHEADER] = array();

            foreach ($this->headers as $key => $value) {
                $curl_opt[CURLOPT_HTTPHEADER][] = sprintf("%s: %s", $key, $value);
            }
        }

        if (!empty($curl_opt[CURLOPT_INFILE])) {
            $curl_opt[CURLOPT_PUT] = true;
        }

        if ($this->resultFileHandle) {
            $curl_opt[CURLOPT_FILE] = $this->resultFileHandle;
            $curl_opt[CURLOPT_HEADER] = false;
        }
        curl_setopt_array($this->handle, $curl_opt);
        return true;
    }
}