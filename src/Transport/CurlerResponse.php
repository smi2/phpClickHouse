<?php

namespace ClickHouseDB\Transport;

class CurlerResponse
{
    /**
     * @var mixed
     */
    public $_headers;

    /**
     * @var mixed
     */
    public $_info;

    /**
     * @var mixed
     */
    public $_error;

    /**
     * @var int
     */
    public $_errorNo = 0;

    /**
     * @var float
     */
    public $_useTime;

    /**
     * @var string
     */
    public $_body;


    /**
     * Response constructor.
     */
    public function __construct() {}


    /**
     * @return int
     */
    public function error_no()
    {
        return $this->_errorNo;
    }

    /**
     * @return mixed
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * @return mixed
     */
    public function url()
    {
        return $this->_info['url'];
    }

    /**
     * @return mixed
     */
    public function total_time()
    {
        return round($this->_info['total_time'], 3);
    }

    /**
     * @return string
     */
    public function starttransfer_time()
    {
        return round($this->_info['starttransfer_time'], 3);
    }

    /**
     * @return string
     */
    public function connect_time()
    {
        return round($this->_info['connect_time'], 3);
    }

    /**
     * @return string
     */
    public function pretransfer_time()
    {
        return round($this->_info['pretransfer_time'], 3);
    }

    /**
     * @return mixed
     */
    public function content_type()
    {
        return $this->_info['content_type'];
    }

    /**
     * @return mixed
     */
    public function http_code()
    {
        return $this->_info['http_code'];
    }

    /**
     * @param string $name
     * @return null|string
     */
    public function headers($name)
    {
        if (isset($this->_headers[$name])) {
            return $this->_headers[$name];
        }

        return null;
    }

    /**
     * @return null|string
     */
    public function connection()
    {
        return $this->headers('Connection');
    }

    /**
     * @return mixed
     */
    public function body()
    {
        return $this->_body;
    }

    /**
     * @return mixed
     */
    public function as_string()
    {
        return $this->body();
    }

    /**
     *
     */
    public function dump_json()
    {
        print_r($this->json());
    }

    public function getDetails(): array
    {
        return [
            'body'    => $this->_body,
            'headers' => $this->_headers,
            'error'   => $this->error(),
            'info'    => $this->_info,
        ];
    }

    /**
     * @param bool $result
     * @return string
     */
    public function dump($result = false)
    {
        $msg = "\n--------------------------- Response -------------------------------------\nBODY:\n";
        $msg .= print_r($this->_body, true);
        $msg .= "\nHEAD:\n";
        $msg .= print_r($this->_headers, true);
        $msg .= "\nERROR:\n" . $this->error();
        $msg .= "\nINFO:\n";
        $msg .= json_encode($this->_info);
        $msg .= "\n----------------------------------------------------------------------\n";

        if ($result) {
            return $msg;
        }

        echo $msg;
    }

    /**
     * @param int $size
     * @param string $unit
     * @return string
     */
    private function humanFileSize($size, $unit = '')
    {
        if ((!$unit && $size >= 1 << 30) || $unit == 'GB') {
            return number_format($size / (1 << 30), 2) . ' GB';
        }
        if ((!$unit && $size >= 1 << 20) || $unit == 'MB') {
            return number_format($size / (1 << 20), 2) . ' MB';
        }
        if ((!$unit && $size >= 1 << 10) || $unit == 'KB') {
            return number_format($size / (1 << 10), 2) . ' KB';
        }

        return number_format($size) . ' bytes';
    }

    /**
     * @return string
     */
    public function upload_content_length()
    {
        return $this->humanFileSize($this->_info['upload_content_length']);
    }

    /**
     * @return string
     */
    public function speed_upload()
    {
        $SPEED_UPLOAD = $this->_info['speed_upload'];
        return round(($SPEED_UPLOAD * 8) / (1000 * 1000), 2) . ' Mbps';
    }

    /**
     * @return string
     */
    public function speed_download()
    {
        $SPEED_UPLOAD = $this->_info['speed_download'];
        return round(($SPEED_UPLOAD * 8) / (1000 * 1000), 2) . ' Mbps';
    }

    /**
     * @return string
     */
    public function size_upload()
    {
        return $this->humanFileSize($this->_info['size_upload']);
    }

    /**
     * @return string
     */
    public function request_size()
    {
        return $this->humanFileSize($this->_info['request_size']);
    }

    /**
     * @return string
     */
    public function header_size()
    {
        return $this->humanFileSize($this->_info['header_size']);
    }

    /**
     * @return string
     */
    public function size_download()
    {
        return $this->humanFileSize($this->_info['size_download']);
    }

    /**
     * @return mixed
     */
    public function info()
    {
        return $this->_info;
    }
    /**
     * @param string|null $key
     * @return bool|mixed
     */
    public function json($key = null)
    {
        $d = json_decode($this->body(), true);

        if (!$key) {
            return $d;
        }

        if (!isset($d[$key])) {
            return false;
        }

        return $d[$key];
    }

    /**
     * @return mixed
     */
    public function rawDataOrJson($format)
    {
        // JSONCompact // JSONEachRow

        if (stripos($format, 'json') !== false)
        {
            if (stripos($format,'JSONEachRow')===false)
            return $this->json();
        }
        return $this->body();

    }
}
