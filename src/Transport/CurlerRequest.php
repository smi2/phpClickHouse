<?php
namespace Curler;
class Request
{
    public $extendinfo=array();

    private $parameters='';
    private $options;
    private $headers; // Parsed reponse header object.
    private $url;
    private $method;
    private $id;
    private $handle;
    private $_cookieFile;
    private $resp;
    private $_persistent=false;
    private $callback_class='';
    private $callback_functionName='';
    private $callback_function=false;

    private $infile_handle=false;

    public function __construct($id=false)
    {
        $this->id=$id;
        $this->header('Accept-Language','ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3');
        $this->header('Cache-Control','no-cache, no-store, must-revalidate');
        $this->header('Expires','0');
        $this->header('Pragma','no-cache');
        $this->options=array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HEADER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_AUTOREFERER        => 1, // при редиректе подставлять в «Referer:» значение из «Location:»
            CURLOPT_BINARYTRANSFER    => 1, // передавать в binary-safe
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 8.1; Trident/5.0; .NET4.0E; en-AU)',
        );

    }

    public function __destructor()
    {
        $this->close();
    }
    public function close()
    {
        curl_close($this->handle);
        $this->handle=null;
    }
    public function sslVeryfi()
    {
        die('@todo sslVeryfi');
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    }
    /**
     * @param $cookieFile
     * @return $this
     */
    public function cookieFile($cookieFile)
    {

        $this->_cookieFile=$cookieFile;
        return $this;
    }
    /**
     * @param bool $set
     *
     * @return $this
     */
    public function id($set=false)
    {
        if ($set)
        $this->id=$set;
        return $this;
    }

    public function extendinfo($params)
    {
        $this->extendinfo=$params;
        return $this;
    }
    public function getExtendinfo($key=null)
    {
        if ($key)
        {
            return isset($this->extendinfo[$key])?$this->extendinfo[$key]:false;
        }
        return $this->extendinfo;
    }

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
        $this->infile_handle=fopen($file_name,'r');

        $this->options[CURLOPT_INFILE]=$this->infile_handle;
        $this->options[CURLOPT_INFILESIZE]=filesize($file_name);
        return $this->infile_handle;
    }
    public function setCallbackFunction($callback)
    {
        $this->callback_function=$callback;
    }
    public function setCallback($classCallBack,$functionName)
    {
        $this->callback_class=$classCallBack;
        $this->callback_functionName=$functionName;
    }
    public function onCallback()
    {
        if ($this->callback_function)
        {
            $x=$this->callback_function;
            $x($this);
        }
        if ($this->callback_class && $this->callback_functionName)
        {
            $c=$this->callback_functionName;
            $this->callback_class->$c($this);
        }
    }
    public function dump()
    {
        echo 'URL:'.$this->url."\n";
        echo 'METHOD:'.$this->method."\n";
        echo 'PARAMS:';
        print_r($this->parameters);
        echo "\n\n";
    }
    public function getId()
    {
        return $this->id;
    }
    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function option($key, $value){
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function persistent()
    {
        $this->_persistent=true;
        return $this;
    }
    public function isPersistent()
    {
        return $this->_persistent;
    }

    /**
     * @return $this
     */
    public function keepAlive($sec=60)
    {
        $this->options[CURLOPT_FORBID_REUSE] = TRUE;
        $this->headers['Connection']='Keep-Alive';
        $this->headers['Keep-Alive']=$sec;
        return $this;
    }
    /**
     * @return $this
     */
    public function verbose($flag=true)
    {
        $this->options[CURLOPT_VERBOSE]=$flag;
        return $this;
    }
    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function header($key,$value)
    {
        $this->headers[$key]=$value;
        return $this;
    }


    /**
     * @param $url
     * @return $this
     */
    public function url($url)
    {
        $this->url=$url;
        return $this;
    }
    public function getUrl()
    {
        return $this->url;
    }

    public function getUniqHash($id)
    {
        return $id.'.'.microtime().mt_rand(0,1000000);
    }

    /**
     * @param $username
     * @param $password
     * @return $this
     */
    public function auth($username,$password)
    {
        $this->options[CURLOPT_USERPWD] = sprintf("%s:%s",$username,$password);
        return $this;

    }


    /**
     * @param $data
     * @return $this
     */
    public function parameters_string($data)
    {
        $this->parameters=http_build_query($data);
        return $this;
    }

    /**
     * @return $this
     */
    public function timeOut($seconds=10)
    {
        $this->options[CURLOPT_TIMEOUT]=$seconds;
        $this->keepAlive(round($seconds/2));
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function parameters_json($data)
    {
        $this->header("Content-Type","application/json, text/javascript; charset=utf-8");
        $this->header("Accept","application/json, text/javascript, */*; q=0.01");
        if ($data===null)
        {
            $this->parameters='{}';
            return $this;
        }
        if (is_string($data))
        {
            $this->parameters=$data;
            return $this;
        }

        $this->parameters=json_encode($data);
        if (!$this->parameters && $data)
        {
            var_dump($data);
            throw new \Exception("Cant json_encode");
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
     * @param $method
     *
     * @return $this
     */
    private function execute($method)
    {
        $this->method=$method;
        return $this;
    }

    /**
     * @return \Curler\Response
     * @throws \Exception
     */
    public function response()
    {
        if (!$this->resp) throw new \Exception('cant fetch response - is empty');
        return $this->resp;
    }

    /**
     * @return bool
     */
    public function isResponseExists()
    {
        return ($this->resp?true:false);
    }

    /**
     * @param Response $resp
     */
    public function setResponse(\Curler\Response $resp)
    {
        $this->resp=$resp;

    }
    public function handle()
    {
        $this->prepareRequest();
        return $this->handle;
    }
    private function prepareRequest()
    {

        if (!$this->handle)
        {
            $this->handle = curl_init();
        }
        //
        $curl_opt=$this->options;

        $method=$this->method;




        if(strtoupper($method) == 'GET'){
            $curl_opt[CURLOPT_HTTPGET] = TRUE;
            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curl_opt[CURLOPT_POSTFIELDS] =false;
        }
        else {

            if (strtoupper($method)==='POST') $curl_opt[CURLOPT_POST] = TRUE;


            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            if ($this->parameters)
            {
                $curl_opt[CURLOPT_POSTFIELDS] = $this->parameters;
                $this->header('Content-Length',strlen($this->parameters));
            }

        }

        $curl_opt[CURLOPT_URL] = $this->url;


        if ($this->_cookieFile)
        {

            $curl_opt[CURLOPT_COOKIEFILE]=$this->_cookieFile;
            $curl_opt[CURLOPT_COOKIEJAR]=$this->_cookieFile;
        }


        //
        if ($this->headers && sizeof($this->headers))
        {
            $curl_opt[CURLOPT_HTTPHEADER] = array();
            foreach( $this->headers as $key => $value){
                $curl_opt[CURLOPT_HTTPHEADER][] = sprintf("%s:%s", $key, $value);
            }


        }

        if (!empty($curl_opt[CURLOPT_INFILE]))
        {
            $curl_opt[CURLOPT_PUT]=true;
        }

        curl_setopt_array($this->handle, $curl_opt);
//        if ($this->curl_progress_callback)  curl_setopt($this->handle, CURLOPT_PROGRESSFUNCTION, array($this, 'curl_progress_callback'));
        return true;

    }

}