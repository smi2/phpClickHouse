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
            $msg="\n-----\nBODY:\n";
            $msg.=print_r($this->_body,true);
            $msg.="\nHEAD:\n";
            $msg.=print_r($this->_headers,true);
            $msg.="\nINFO:\n";
            $msg.=print_r($this->_info,true);
            $msg.="\n-----\n";

        if ($result) return $msg;
        echo $msg;
    }
    public function json($key=null)
    {
        $d=json_decode($this->body(),true);
        if (!$key) return $d;

        if (!isset($d[$key])) return false;
        return $d[$key];


    }
}