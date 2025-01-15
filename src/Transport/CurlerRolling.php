<?php

namespace ClickHouseDB\Transport;

use ClickHouseDB\Exception\TransportException;

class CurlerRolling
{
    const SLEEP_DELAY = 1000; // 1ms

    /**
     * @var int
     *
     * Max number of simultaneous requests.
     */
    private $simultaneousLimit = 10;

    /**
     * @var array
     *
     * Requests currently being processed by curl
     */
    private $activeRequests = [];

    /**
     * @var int
     */
    private $runningRequests = 0;

    /**
     * @var CurlerRequest[]
     *
     * Requests queued to be processed
     */
    private $pendingRequests = [];

    /**
     * @return int
     */
    private $completedRequestCount = 0;

    /**
     * @var null|resource
     */
    private $_pool_master = null;

    /**
     * @var int
     */
    private $waitRequests = 0;

    /**
     * @var array
     */
    private $handleMapTasks = [];

    /**
     *
     */
    public function __destruct()
    {
        $this->close();
    }


    /**
     * @return resource
     */
    private function handlerMulti()
    {
        if (!$this->_pool_master) {
            $this->_pool_master = curl_multi_init();

            if (function_exists('curl_multi_setopt')) {
                curl_multi_setopt($this->_pool_master, CURLMOPT_MAXCONNECTS, $this->simultaneousLimit);
            }
        }

        return $this->_pool_master;
    }

    /**
     *
     */
    public function close()
    {
        if ($this->_pool_master) {
            curl_multi_close($this->handlerMulti());
        }
    }


    /**
     * @param CurlerRequest $req
     * @param bool $checkMultiAdd
     * @param bool $force
     * @return bool
     * @throws TransportException
     */
    public function addQueLoop(CurlerRequest $req, $checkMultiAdd = true, $force = false)
    {
        $id = $req->getId();

        if (!$id) {
            $id = $req->getUniqHash($this->completedRequestCount);
        }

        if (!$force && isset($this->pendingRequests[$id])) {
            if (!$checkMultiAdd) {
                return false;
            }

            throw new TransportException("Cant add exists que - cant overwrite : $id!\n");
        }

        $this->pendingRequests[$id] = $req;
        return true;
    }

    /**
     * @param resource $oneHandle
     * @return CurlerResponse
     */
    private function makeResponse($oneHandle)
    {
        $response = curl_multi_getcontent($oneHandle);
        $header_size = curl_getinfo($oneHandle, CURLINFO_HEADER_SIZE);
        $header = substr($response ?? '', 0, $header_size);
        $body = substr($response ?? '', $header_size);

        $n = new CurlerResponse();
        $n->_headers = $this->parse_headers_from_curl_response($header);
        $n->_body = $body;
        $n->_info = curl_getinfo($oneHandle);
        $n->_error = curl_error($oneHandle);
        $n->_errorNo = curl_errno($oneHandle);
        $n->_useTime = 0;

        return $n;
    }

    /**
     * @return bool
     * @throws TransportException
     */
    public function execLoopWait()
    {
        do {
            $this->exec();
            usleep(self::SLEEP_DELAY);
        } while (($this->countActive() + $this->countPending()) > 0);

        return true;
    }

    /**
     * @param string $response
     * @return array
     */
    private function parse_headers_from_curl_response($response)
    {
        $headers = [];
        $header_text = $response;

        foreach (explode("\r\n", $header_text) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                $r = explode(': ', $line);
                if (sizeof($r) == 2) {
                    $headers[$r[0]] = $r[1];
                }
            }
        }

        return $headers;
    }

    /**
     * @return int
     */
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
     * @return int
     */
    public function countCompleted()
    {
        return $this->completedRequestCount;
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

    /**
     * @return int
     */
    public function getRunningRequests()
    {
        return $this->runningRequests;
    }

    /**
     * @param CurlerRequest $request
     * @param bool $auto_close
     * @return mixed
     * @throws TransportException
     */
    public function execOne(CurlerRequest $request, $auto_close = false)
    {
        $h = $request->handle();
        curl_exec($h);

        $request->setResponse($this->makeResponse($h));

        if ($auto_close) {
            $request->close();
        }

        return $request->response()->http_code();
    }

    /**
     * @return string
     */
    public function getInfo()
    {
        return "runningRequests = {$this->runningRequests} , pending=" . sizeof($this->pendingRequests) . " ";
    }

    /**
     * @throws TransportException
     */
    public function exec()
    {
        $this->makePendingRequestsQue();

        // ensure we're running
        // a request was just completed -- find out which one

        while (($execrun = curl_multi_exec($this->handlerMulti(), $running)) == CURLM_CALL_MULTI_PERFORM);

        if ($execrun != CURLM_OK) {
            throw new TransportException("[ NOT CURLM_OK ]");
        }

        $this->runningRequests = $running;

        while ($done = curl_multi_info_read($this->handlerMulti())) {
            $response = $this->makeResponse($done['handle']);

            // send the return values to the callback function.


            if (is_object($done['handle'])) {
                $key = spl_object_id( $done['handle'] );
            } else {
                $key = (string) $done['handle'] ;
            }

            $task_id = $this->handleMapTasks[$key];
            $request = $this->pendingRequests[$this->handleMapTasks[$key]];

            unset($this->handleMapTasks[$key]);
            unset($this->activeRequests[$task_id]);

            $this->pendingRequests[$task_id]->setResponse($response);
            $this->pendingRequests[$task_id]->onCallback();


            if (!$request->isPersistent()) {
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
        return $this->countActive();
    }

    public function makePendingRequestsQue()
    {
        $max = $this->getSimultaneousLimit();
        $active = $this->countActive();


        if ($active < $max) {
            $canAdd = $max - $active;
//            $pending = sizeof($this->pendingRequests);

            $add = [];


            foreach ($this->pendingRequests as $task_id => $params) {
                if (empty($this->activeRequests[$task_id])) {
                    $add[$task_id] = $task_id;
                }
            }


            if (sizeof($add)) {
                if ($canAdd >= sizeof($add)) {
                    $ll = $add;
                } else {
                    $ll = array_rand($add, $canAdd);
                    if (!is_array($ll)) {
                        $ll = array($ll => $ll);
                    }
                }

                foreach ($ll as $task_id) {
                    $this->_prepareLoopQue($task_id);
                }
            }// if add
        }// if can add
    }

    /**
     * @param string $task_id
     */
    private function _prepareLoopQue($task_id)
    {
        $this->activeRequests[$task_id] = 1;
        $this->waitRequests++;

        $h = $this->pendingRequests[$task_id]->handle();

        // pool
        curl_multi_add_handle($this->handlerMulti(), $h);

        if (is_object($h)) {
            $key = spl_object_id( $h );
        } else {
            $key = (string) $h ;
        }

        $this->handleMapTasks[$key] = $task_id;
    }
}
