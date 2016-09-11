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
    public function getAllHostsIps()
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
            if ($replica['is_readonly']) $ok=false;
            if ($replica['is_session_expired']) $ok=false;
            if ($replica['future_parts']>20) $ok=false;
            if ($replica['parts_to_check']>10) $ok=false;
            if ($replica['total_replicas']<2) $ok=false;
            if ($replica['active_replicas'] < $replica['total_replicas']) $ok=false;
            if ($replica['queue_size']>20) $ok=false;
            if (($replica['log_max_index'] - $replica['log_pointer'])>10) $ok=false;
            if (!$ok) break;
        }
        return $ok;
    }
    public function rescan()
    {
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
            }
            // ---------------------------------------------------------------------------------------------------
            try
            {
                $result['clusters'][$ip] =$statementsClusters[$ip]->rowsAsTree('cluster.host_address');
            }
            catch (\Exception $E)
            {
                $result['clusters'][$ip] = false;
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

        // Создаем клиенты под каждый IP
        foreach ($this->ips as $ip)
        {
            if (empty($this->clients[$ip]))
            {
                $this->clients[$ip]=clone $this->defaultClient();
                $this->clients[$ip]->setHost($ip);
            }
        }
        $this->isScaned=true;
        $this->replicasIsOk=$replicasIsOk;
        // ------------------------------------------------
        // @todo Уточнить на боевых падениях и при разношорсных конфигурациях...
        if (sizeof($this->badIps))
        {
            $this->replicasIsOk=false;
        }

        // @todo Мы подключаемся ко всем в списке DNS, нужно пререить что запросы вернули все хосты к которым мы подключались
    }

    /**
     * @return Client
     */
    public function client($ip)
    {
        $this->rescan();
        return $this->clients[$ip];
    }
    /**
     * @return Client
     */
    public function activeClient()
    {
        return $this->client($this->ips[0]);
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