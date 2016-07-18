<?php
namespace Curler;
class Curler
{
    private $_pool_master=null;
    private $_max_que=15;
    private $que=array();
    private $handleMap=array();

    private $_tasks_runs=array();
    private $_count_done=0;
    private $_count_wait=0;
    private $callback=false;
    private $callback_function=false;
    private $_count_running=0;

    public function __construct()
    {

    }
    public function __destructor()
    {
        $this->close();
    }
    public function maxQue($set=false)
    {
        if ($set)
        {
            $this->_max_que=$set;
        }
        return $this;
    }


    /**
     * @return resource
     */
    private function handlerMulti()
    {
        if (!$this->_pool_master)
        {
            $this->_pool_master= curl_multi_init();
            if (function_exists('curl_multi_setopt'))
                curl_multi_setopt($this->_pool_master,CURLMOPT_MAXCONNECTS,$this->_max_que);
        }
        return $this->_pool_master;

    }
    public function close()
    {
        if ($this->_pool_master)
        curl_multi_close($this->handlerMulti());
    }


    public function addQueLoop(\Curler\Request $req)
    {
        $id=$req->getId();
        if (!$id) $id=$req->getUniqHash($this->_count_done);
        $this->que[$id]=$req;
    }
    /**
     * @param $oneHandle
     * @param $response
     *
     * @return string
     */
    public function execOne(\Curler\Request $req,$auto_close=false)
    {
        $h=$req->handle();
        curl_exec($h);
        $req->setResponse($this->makeResponse($h));
        if ($auto_close) $req->close();
        return $req->response()->http_code();
    }

    /**
     * @param $oneHandle
     * @param $response
     *
     * @return Response
     */
    private function makeResponse($oneHandle)
    {
        $response = curl_multi_getcontent($oneHandle);
        $header_size = curl_getinfo($oneHandle, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $n=new \Curler\Response();
        $n->_headers=$this->parse_headers_from_curl_response($header);
        $n->_body=$body;
        $n->_info = curl_getinfo($oneHandle);
        $n->_error = curl_error($oneHandle);
        $n->_useTime=0;
        return $n;
    }


    private  function parse_headers_from_curl_response($response)
    {
        $headers = array();
        $header_text = $response;
        foreach (explode("\r\n", $header_text) as $i => $line)
            if ($i === 0)
                $headers['http_code'] = $line;
            else
            {
                $r= explode(': ', $line);
                if (sizeof($r)==2)
                    $headers[$r[0]] = $r[1];
            }
        return $headers;
    }

    public function getInfo()
    {
        return "wait:".$this->_count_wait." // done:".$this->_count_done." // runs:".$this->_count_running. " :: ".$this->_lashmakeQue_state;
    }

    public function execLoopWait($usleep=25000)
    {
        $this->_count_done=0;
        $this->_count_wait=0;
        $this->_count_running=0;
        // add all tasks
        $this->makeQue(true);
        $c=0;
        do
        {
            $this->loop(true);
            usleep($usleep);
            echo date('H:i:s').$this->getInfo()."\n";
            $loop=($this->_count_wait>0?true:false);
            $c++;
            if ($c>100000) break;
        } while ($loop);

    }

    public function setCallback($callback,$callbackFunction)
    {
        $this->callback_function=$callbackFunction;
        $this->callback=$callback;
    }
    public function execLoopPersistent()
    {
        $this->makeQue(false);

        return $this->loop(false);
    }

    private function loop($waitAnswer=false)
    {
        $done=true;
        // a request was just completed -- find out which one

        while(($execrun = curl_multi_exec($this->handlerMulti(), $running)) == CURLM_CALL_MULTI_PERFORM);
        if($execrun != CURLM_OK)
        {
            throw new \Exception("[ NOT CURLM_OK]");
        }
        $this->_count_running=$running;
        $status=null;
        while($done = curl_multi_info_read($this->handlerMulti())) {
            // request successful.  process output using the callback function.
            $resp=$this->makeResponse($done['handle']);

            $key = (string) $done['handle'];
            $task_id = $this->handleMap[$key];
            $this->handleMap[$key]=null;
            $this->_tasks_runs[$task_id]=null;
            unset($this->handleMap[$key]);
            unset($this->_tasks_runs[$task_id]);




            $this->_count_wait--;
            $this->_count_done++;

//            echo "DONE:[".$this->getInfo()."]\n";



            $this->que[$task_id]->setResponse($resp);
            $this->que[$task_id]->onCallback();

//            $this->__callback($task_id);
            if (!$waitAnswer)
            {
                $this->makeQue($waitAnswer);
            }

            curl_multi_remove_handle($this->handlerMulti(), $done['handle']);



            // if something was requeued, this will get it running/update our loop check values
            $status = curl_multi_exec($this->handlerMulti(), $running);

        }
        // Error detection -- this is very, very rare
        $err = null;
        switch ($status) {
            case CURLM_BAD_EASY_HANDLE:
                $err = 'CURLM_BAD_EASY_HANDLE';
                break;
            case CURLM_OUT_OF_MEMORY:
                $err = 'CURLM_OUT_OF_MEMORY';
                break;
            case CURLM_INTERNAL_ERROR:
                $err = 'CURLM_INTERNAL_ERROR';
                break;
            case CURLM_BAD_HANDLE:
                $err = 'CURLM_BAD_HANDLE';
                break;
        }
        if ($err) {
            throw new \Exception("curl_multi_exec failed with error code ($status) const ($err)");
        }


        $this->_count_running=$running;

        if (!$waitAnswer && $done===false)
        {
            $this->makeQue($waitAnswer);
        }
        curl_multi_select($this->handlerMulti(), 0.01);
        return $this->_count_running;
    }
    public function __callback($task_id)
    {
        if (!$this->callback) return false;
        $call=$this->callback_function;
        $this->callback->$call($this->que[$task_id]);
    }

    private $_lashmakeQue_state='';
    public function makeQue($makeAllTogether=false)
    {
        $this->_lashmakeQue_state="";
        if ($makeAllTogether)
        {
            foreach ($this->que as $task_id => $params) {
                $this->prepareLoopQue($task_id);
            }
            return true;
        }


        //

        $max=$this->_max_que;
        if ($this->_count_running<$max) {

            $need=$max-$this->_count_running;
            $que_size=sizeof($this->que);
            $can=array();


            $this->_lashmakeQue_state.=" can:$need :que=$que_size:tasks=".sizeof($this->_tasks_runs);

            foreach ($this->que as $task_id => $params) {
                if (empty($this->_tasks_runs[$task_id])) {
                    $can[$task_id]=$task_id;
                    $this->_lashmakeQue_state.='{}';
                }
            }
            $this->_lashmakeQue_state.=' sizeCan='.sizeof($can);

            if (sizeof($can))
            {
                if ($need>=sizeof($can))
                {
                    $ll=$can;
                }
                else
                {
                    $ll=array_rand($can,$need);
                    if (!is_array($ll)) {  $ll=array($ll=>$ll); }

                }

                foreach ($ll as $task_id) {


                    if (empty($this->_tasks_runs[$task_id])) {
                        $this->_lashmakeQue_state.=' []';
                        $this->prepareLoopQue($task_id);
                    }
                }
            }
        }


    }

    private function prepareLoopQue($task_id)
    {
        $this->_tasks_runs[$task_id] = 1;
//        echo "prepareLoopQue($task_id);\n";
        $this->_count_wait++;
        //
        $h=$this->que[$task_id]->handle();
        // pool
        curl_multi_add_handle($this->handlerMulti(),$h);
        $key=(string) $h;
        $this->handleMap[$key]=$task_id;
    }



}
