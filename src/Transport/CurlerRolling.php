<?php
namespace Curler;
class CurlerRolling
{
    /**
     * @var int
     *
     * Max number of simultaneous requests.
     */
    private $simultaneousLimit = 5;
    /**
     * @var Request[]
     *
     * Requests currently being processed by curl
     */
    private $activeRequests = array();

    private $runningRequests = 0 ;
    /**
     * @var Request[]
     *
     * Requests queued to be processed
     */
    private $pendingRequests = array();
    /**
     * @return int
     */
    private $completedRequestCount=0;


    private $_pool_master=null;


    private $waitRequests=0;
    private $handleMapTasks=array();


    public function __construct()
    {

    }
    public function __destructor()
    {
        $this->close();
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
                curl_multi_setopt($this->_pool_master,CURLMOPT_MAXCONNECTS,$this->simultaneousLimit);
        }
        return $this->_pool_master;

    }
    public function close()
    {
        if ($this->_pool_master)
            curl_multi_close($this->handlerMulti());
    }
    public function updateQueLoop(\Curler\Request $req)
    {
        $id=$req->getId();
        if (!$id) $id=$req->getUniqHash($this->completedRequestCount);
        $this->pendingRequests[$id]=$req;
        return true;
    }
    public function addQueLoop(\Curler\Request $req,$checkMultiAdd=true,$force=false)
    {
        $id=$req->getId();
        if (!$id) $id=$req->getUniqHash($this->completedRequestCount);


        if (!$force && isset($this->pendingRequests[$id]))
            {
                if (!$checkMultiAdd)
                {
                    return false;
                }
                else
                {
                    throw new \ClickHouseDB\TransportException("Cant add exists que - cant overwrite : $id!\n");
                }

            }
        $this->pendingRequests[$id]=$req;
        return true;
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
        $n->_errorNo = curl_errno($oneHandle);
        $n->_useTime=0;
        return $n;
    }

    /**
     * @param int $usleep
     */
    public function execLoopWait($usleep=10000)
    {
        // @todo rewrite wait
        $c=0;
        // add all tasks
        do
        {
            $this->exec();
            usleep($usleep);
            $loop=$this->countActive();
            $c++;
            if ($c>100000) break;
        } while ($loop);
        return true;
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
    public function countPending()
    {
        return sizeof($this->pendingRequests);
    }
    /**
     * @return int
     */
    public function countActive()
    {
        return count($this->activeRequests);
    }

    /**
     * @param bool $useArray count the completedRequests array is true. Otherwise use the global counter.
     * @return int
     */
    public function countCompleted()
    {
        return  $this->completedRequestCount;
    }

    /**
     * Set the limit for how many cURL requests will be execute simultaneously.
     *
     * Please be mindful that if you set this too high, requests are likely to fail
     * more frequently or automated software may perceive you as a DOS attack and
     * automatically block further requests.
     *
     * @param int $count
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setSimultaneousLimit($count)
    {
        if (!is_int($count) || $count < 2) {
            throw new \InvalidArgumentException("setSimultaneousLimit count must be an int >= 2");
        }
        $this->simultaneousLimit = $count;
        return $this;
    }
    /**
     * @return int
     */
    public function getSimultaneousLimit()
    {
        return $this->simultaneousLimit;
    }
    public function getRunningRequests()
    {
        return $this->runningRequests;
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

    public function getInfo()
    {
        return "runningRequests = {$this->runningRequests} , pending=".sizeof($this->pendingRequests)." ";
    }
    public function exec()
    {
        $this->makePendingRequestsQue();

        // ensure we're running

        // a request was just completed -- find out which one

        while(($execrun = curl_multi_exec($this->handlerMulti(), $running)) == CURLM_CALL_MULTI_PERFORM);
        if($execrun != CURLM_OK)
        {
            throw new \ClickHouseDB\TransportException("[ NOT CURLM_OK]");
        }
        $this->runningRequests=$running;

        while ($done = curl_multi_info_read($this->handlerMulti())) {
            $response=$this->makeResponse($done['handle']);

            // send the return values to the callback function.

            $key = (string) $done['handle'];
            $task_id = $this->handleMapTasks[$key];
            $request = $this->pendingRequests[$this->handleMapTasks[$key]];
            unset($this->handleMapTasks[$key]);
            unset($this->activeRequests[$task_id]);

            $this->pendingRequests[$task_id]->setResponse($response);
            $this->pendingRequests[$task_id]->onCallback();


            if (!$request->isPersistent())
            {
                unset($this->pendingRequests[$task_id]);

            }
            $this->completedRequestCount++;

            // remove the curl handle that just completed
            curl_multi_remove_handle($this->handlerMulti(), $done['handle']);

            // if something was requeued, this will get it running/update our loop check values
            $status = curl_multi_exec($this->handlerMulti(), $active);
        }
        // see if there is anything to read
        curl_multi_select($this->handlerMulti(), 0.01);
    }


    private $_lashmakeQue_state='';
    public function makePendingRequestsQue()
    {
        $this->_lashmakeQue_state="";

        $max=$this->getSimultaneousLimit();
        $active=$this->countActive();

        $this->_lashmakeQue_state.="Active=$active | Max=$max |";

        if ($active<$max)
        {

            $canAdd=$max-$active;
            $pending=sizeof($this->pendingRequests);


            $add=array();


            $this->_lashmakeQue_state.=" canAdd:$canAdd | pending=$pending |";

            foreach ($this->pendingRequests as $task_id => $params) {
                if (empty($this->activeRequests[$task_id]))
                {
                    $add[$task_id]=$task_id;
                    $this->_lashmakeQue_state.='{A}';
                }
            }
            $this->_lashmakeQue_state.=' sizeAdd='.sizeof($add);

            if (sizeof($add))
            {
                if ($canAdd>=sizeof($add))
                {
                    $ll=$add;
                }
                else
                {
                    $ll=array_rand($add,$canAdd);
                    if (!is_array($ll)) {  $ll=array($ll=>$ll); }
                }

                foreach ($ll as $task_id) {
                        $this->_prepareLoopQue($task_id);
                }

            }// if add
        }// if can add
    }

    private function _prepareLoopQue($task_id)
    {
        $this->activeRequests[$task_id] = 1;

        $this->waitRequests++;
        //
        $h=$this->pendingRequests[$task_id]->handle();
        // pool
        curl_multi_add_handle($this->handlerMulti(),$h);
        $key=(string) $h;
        $this->handleMapTasks[$key]=$task_id;
    }
}