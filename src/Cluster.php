<?php
namespace ClickHouseDB;

class Cluster
{


    /**
     * @var array
     */
    private $ips=[];


    /**
     * @var Client[]
     */
    private $clients=[];

    /**
     * @var Client
     */
    private $defaultClient;

    /**
     * @var array
     */
    private $badIps=[];

    /**
     * @var string
     */
    private $error="";
    /**
     * @var array
     */
    private $resultScan=[];
    /**
     * @var bool
     */
    private $defaultHostName;

    /**
     * @var int
     */
    private $scanTimeOut=2;

    /**
     * @var bool
     */
    private $isScaned=false;

    /**
     * @var bool
     */
    private $replicasIsOk;

    /**
     * Cluster constructor.
     * @param $connect_params
     * @param array $settings
     * @param int $scanTimeOut
     */
    public function __construct($connect_params, $settings = [])
    {
        $this->defaultClient=new Client($connect_params,$settings);
        $this->defaultHostName=$this->defaultClient->getConnectHost();
        $this->setIps(gethostbynamel($this->defaultHostName));
    }

    /**
     * @return Client
     */
    private function defaultClient()
    {
        return $this->defaultClient;
    }
    public function setScanTimeOut($scanTimeOut)
    {
        $this->scanTimeOut = $scanTimeOut;
    }

    public function setIps($hosts_ips)
    {
        $this->ips = $hosts_ips;
    }

    /**
     * @return array
     */
    public function getIps()
    {
        return $this->ips;
    }

    /**
     * @return array
     */
    public function getBadIps()
    {
        return $this->badIps;
    }


    /**
     * @return $this
     */
    public function connect()
    {
        if (!$this->isScaned)
        {
            $this->rescan();
        }
        return $this;
    }

    /**
     * @param $replicas
     * @return bool
     */
    private function isReplicasWork($replicas)
    {
        $ok=true;
        if (!is_array($replicas))
        {
            // @todo нет массива ошибка, т/к мы работем с репликами?
            // @todo Как быть есть в кластере НЕТ реплик ?
            return false;
        }
        foreach ($replicas as $replica) {
            if ($replica['is_readonly']) {$ok=false;$this->error[]='is_readonly : '.json_encode($replica);}
            if ($replica['is_session_expired']) {$ok=false;$this->error[]='is_session_expired : '.json_encode($replica);}
            if ($replica['future_parts']>20) {$ok=false;$this->error[]='future_parts : '.json_encode($replica);}
            if ($replica['parts_to_check']>10) {$ok=false;$this->error[]='parts_to_check : '.json_encode($replica);}
            if ($replica['total_replicas']<2) {$ok=false;$this->error[]='total_replicas : '.json_encode($replica);}
            if ($replica['active_replicas'] < $replica['total_replicas']) {$ok=false;$this->error[]='active_replicas : '.json_encode($replica);}
            if ($replica['queue_size']>20) {$ok=false;$this->error[]='queue_size : '.json_encode($replica);}
            if (($replica['log_max_index'] - $replica['log_pointer'])>10) {$ok=false;$this->error[]='log_max_index : '.json_encode($replica);}
            if (!$ok) break;
        }
        return $ok;
    }

    /**
     * @return $this
     */
    public function rescan()
    {
        $this->error='';
       /*
        * 1) Получаем список IP
        * 2) К каждому подключаемся по IP, через activeClient подменяя host на ip
        * 3) Достаем информацию system.clusters + system.replicas c каждой машины , overwrite { DnsCache + timeOuts }
        * 4) Определяем нужные машины для кластера/реплики
        * 5) .... ?
        */
        $statementsReplicas=[];
        $statementsClusters=[];
        $result=[];

        $badIps=[];
        $replicasIsOk=true;

        foreach ($this->ips as $ip)
        {
            $this->defaultClient()->setHost($ip);
            $statementsReplicas[$ip] = $this->defaultClient()->selectAsync('SELECT * FROM system.replicas');
            $statementsClusters[$ip] = $this->defaultClient()->selectAsync('SELECT * FROM system.clusters');
            // пересетапим timeout
            $statementsReplicas[$ip]->getRequest()->setDnsCache(0)->timeOut($this->scanTimeOut)->connectTimeOut($this->scanTimeOut);
            $statementsClusters[$ip]->getRequest()->setDnsCache(0)->timeOut($this->scanTimeOut)->connectTimeOut($this->scanTimeOut);
        }
        $this->defaultClient()->executeAsync();


        foreach ($this->ips as $ip)
        {
            try
            {
                $result['replicas'][$ip] = $statementsReplicas[$ip]->rows();
            }
            catch (\Exception $E)
            {
                $result['replicas'][$ip]= false;
                $badIps[$ip]=$E->getMessage();
                $this->error[]=$E->getMessage();
            }
            // ---------------------------------------------------------------------------------------------------
            try
            {
                $c=$statementsClusters[$ip]->rows();
                $result['clusters'][$ip] = $c;
                foreach ($c as $row)
                {
                    $result['cluster.list'][$row['cluster']][$row['host_address']][$row['shard_num']][$row['replica_num']]=['shard_weight'=>$row['shard_weight'],'is_local'=>$row['is_local']];
                }

            }
            catch (\Exception $E)
            {
                $result['clusters'][$ip] = false;

                $this->error[]=$E->getMessage();
                $badIps[$ip]=$E->getMessage();

            }
            // ---------------------------------------------------------------------------------------------------
            // Проверим что репликации хорошо идут
            $rIsOk= $this->isReplicasWork($result['replicas'][$ip]);
            $result['replicasIsOk'][$ip]=$rIsOk;
            if (!$rIsOk) $replicasIsOk=false;
            // ---------------------------------------------------------------------------------------------------
        }
        // $badIps = array(6) {  '222.222.222.44' =>  string(13) "HttpCode:0 ; " , '222.222.222.11' =>  string(13) "HttpCode:0 ; "
        $this->badIps=$badIps;

        // Востановим DNS имя хоста в клиенте
        $this->defaultClient()->setHost($this->defaultHostName);


        $this->isScaned=true;
        $this->replicasIsOk=$replicasIsOk;
        $this->error[]="Bad replicasIsOk, in ".json_encode($result['replicasIsOk']);
        // ------------------------------------------------
        // @todo Уточнить на боевых падениях и при разношорсных конфигурациях...
        if (sizeof($this->badIps))
        {
            $this->error[]='Have bad ip : '.json_encode($this->badIps);
            $this->replicasIsOk=false;
        }
        $this->error=false;
        $this->resultScan=$result;
        // @todo Мы подключаемся ко всем в списке DNS, нужно пререить что запросы вернули все хосты к которым мы подключались
        return $this;
    }

    /**
     * @return boolean
     */
    public function isReplicasIsOk()
    {
        return $this->connect()->replicasIsOk;
    }
    /**
     * @return Client
     */
    public function client($ip)
    {
        // Создаем клиенты под каждый IP
        if (empty($this->clients[$ip]))
        {
            $this->clients[$ip]=clone $this->defaultClient();
            $this->clients[$ip]->setHost($ip);
        }

        return $this->clients[$ip];
    }
    /**
     * @return Client
     */
    public function activeClient()
    {
        return $this->client($this->ips[0]);
    }
    public function getClusterHosts($cluster)
    {
        $this->connect();
        if (empty($this->resultScan['cluster.list'][$cluster])) throw new QueryException('Cluster not find:'.$cluster);
        return array_keys($this->resultScan['cluster.list'][$cluster]);
    }
    public function getClusterList()
    {
        $this->connect();
        return array_keys($this->resultScan['cluster.list']);
    }

    /**
     * @return string
     */
    public function getError()
    {
        if (is_array($this->error))
        {
            return implode(" ; ".$this->error);
        }
        return $this->error;
    }

    public function createCluster($sql_up,$sql_down,$ip_hosts=[])
    {
        if (!sizeof($ip_hosts)) $ip_hosts=$this->ips;

        if (!is_array($sql_down))
        {
            $sql_down=[$sql_down];
        }
        if (!is_array($sql_up))
        {
            $sql_up=[$sql_up];
        }
        // Пропингуем все хосты
        foreach ($ip_hosts as $ip) {
            try {
                $this->client($ip)->ping();
            } catch (QueryException $E) {
                $this->error = "Can`t connect or ping ip : " . $ip;
                return false;
            }
        }



        // Выполняем запрос на каждый client(IP) , если хоть одни не отработал то делаем на каждый Down
        $need_undo=false;
        $undo_ip=[];
        foreach ($ip_hosts as $ip)
        {
            foreach ($sql_up as $s_u) {
                try {
                    if ($this->client($ip)->write($s_u)->isError()) {
                        $need_undo = true;
                        $this->error = "Host $ip result error";
                    }

                } catch (QueryException $E) {
                    $need_undo = true;
                    $this->error = "Host $ip result error : " . $E->getMessage();
                }
                if ($need_undo)
                {
                    $undo_ip[$ip]=1;
                    break;
                }
            }
            if ($need_undo)
            {
                $undo_ip[$ip]=1;
                break;
            }
        }

        if (!$need_undo)
        {
            return true;
        }

        // if Undo
        // тут не очень точный метод отката
        foreach ($undo_ip as $ip=>$tmp)
        {
            foreach ($sql_down as $s_u) {
                    if ($this->client($ip)->write($s_u)->isError()) {
                }
            }
        }
        return false;

    }
    /**
     * @param $sql
     * @param array $bindings
     * @param bool $exception
     * @return Statement
     */
    public function writeCluster($cluster,$sql, $bindings = [], $exception = true)
    {
        return $this->transport()->write($sql, $bindings, $exception);
    }



/*
system.clusters
Содержит информацию о доступных в конфигурационном файле кластерах и серверах, которые в них входят.
Столбцы:

cluster String      - имя кластера
shard_num UInt32    - номер шарда в кластере, начиная с 1
shard_weight UInt32 - относительный вес шарда при записи данных
replica_num UInt32  - номер реплики в шарде, начиная с 1
host_name String    - имя хоста, как прописано в конфиге
host_address String - IP-адрес хоста, полученный из DNS
port UInt16         - порт, на который обращаться для соединения с сервером
user String         - имя пользователя, которого использовать для соединения с сервером



 */
}