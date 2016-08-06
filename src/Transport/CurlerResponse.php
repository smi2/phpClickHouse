<?php

namespace Curler;
class Response
{
    public $_headers;
    public $_info;
    public $_error;
    public $_useTime;
    public $_body;

    public function __construct()
    {

    }
    public function error()
    {
        return $this->_error;
    }
    public function url()
    {
        return $this->_info['url'];
    }
    public function total_time()
    {
        return $this->_info['total_time'];
    }
    public function content_type()
    {
        return $this->_info['content_type'];
    }
    public function http_code()
    {
        return $this->_info['http_code'];
    }
    public function headers($name)
    {
        if (isset($this->_headers[$name])) return $this->_headers[$name];
        return null;
    }
    public function connection()
    {
        return $this->headers('Connection');
    }
    public function body()
    {
        return $this->_body;
    }
    public function as_string()
    {
        return $this->body();
    }
    public function dump_json()
    {
        print_r($this->json());
    }
    public function dump($result=false)
    {
            $msg="\n--------------------------- Response -------------------------------------\nBODY:\n";
            $msg.=print_r($this->_body,true);
            $msg.="\nHEAD:\n";
            $msg.=print_r($this->_headers,true);
            $msg.="\nERROR:\n".$this->error();
            $msg.="\nINFO:\n";
            $msg.=json_encode($this->_info);
            $msg.="\n----------------------------------------------------------------------\n";

        if ($result) return $msg;
        echo $msg;
    }
    private function humanFileSize($size,$unit="") {
        if( (!$unit && $size >= 1<<30) || $unit == "GB")
            return number_format($size/(1<<30),2)." GB";
        if( (!$unit && $size >= 1<<20) || $unit == "MB")
            return number_format($size/(1<<20),2)." MB";
        if( (!$unit && $size >= 1<<10) || $unit == "KB")
            return number_format($size/(1<<10),2)." KB";
        return number_format($size)." bytes";
    }

    public function upload_content_length()
    {
        return $this->humanFileSize($this->_info['upload_content_length']);
    }
    public function speed_upload()
    {
        $SPEED_UPLOAD=$this->_info['speed_upload'];
        return round(($SPEED_UPLOAD*8)/(1000*1000),2).' Mbps';
    }
    public function size_upload()
    {
        return $this->humanFileSize($this->_info['size_upload']);
    }
    public function json($key=null)
    {
        $d=json_decode($this->body(),true);
        if (!$key) return $d;

        if (!isset($d[$key])) return false;
        return $d[$key];


    }
}