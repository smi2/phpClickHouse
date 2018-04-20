<?php
namespace ClickHouseDB;

use ClickHouseDB\Exception\QueryException;

class Cluster
{
    /**
     * @var array
     */
    private $nodes = [];


    /**
     * @var Client[]
     */
    private $clients = [];

    /**
     * @var Client
     */
    private $defaultClient;

    /**
     * @var array
     */
    private $badNodes = [];

    /**
     * @var array
     */
    private $error = [];
    /**
     * @var array
     */
    private $resultScan = [];
    /**
     * @var bool
     */
    private $defaultHostName;

    /**
     * @var int
     */
    private $scanTimeOut=10;

    /**
     * @var array
     */
    private $tables = [];

    /**
     * @var array
     */
    private $hostsnames = [];
    /**
     * @var bool
     */
    private $isScaned = false;


    /**
     * Признак напрягать CH при проверке кластера запросом в Zookiper
     * false - отправлять запрос в ZK, точнне делать SELECT * FROM system.replicas
     *
     * @var bool
     */
    private $softCheck = false;

    /**
     * @var bool
     */
    private $replicasIsOk;

    /**
     * Кэш
     * @var array
     */
    private $_table_size_cache=[];

    /**
     * Cluster constructor.
     * @param $connect_params
     * @param array $settings
     * @param int $scanTimeOut
     */
    public function __construct($connect_params, $settings = [])
    {
        $this->defaultClient = new Client($connect_params, $settings);
        $this->defaultHostName = $this->defaultClient->getConnectHost();
        $this->setNodes(gethostbynamel($this->defaultHostName));
    }

    /**
     * @return Client
     */
    private function defaultClient()
    {
        return $this->defaultClient;
    }

    /**
     * @param $softCheck
     */
    public function setSoftCheck($softCheck)
    {
        $this->softCheck = $softCheck;
    }

    /**
     * @param $scanTimeOut
     */
    public function setScanTimeOut($scanTimeOut)
    {
        $this->scanTimeOut = $scanTimeOut;
    }

    /**
     * @param $nodes
     */
    public function setNodes($nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * @return array
     */
    public function getNodes()
    {
        return $this->nodes;
    }

    /**
     * @return array
     */
    public function getBadNodes()
    {
        return $this->badNodes;
    }


    /**
     * Connect all nodes and scan
     *
     * @return $this
     */
    public function connect()
    {
        if (!$this->isScaned) {
            $this->rescan();
        }
        return $this;
    }

    /**
     * Проверяете состояние кластера, запрос взят из документации к CH
     * total_replicas<2 - не подходит для без репликационных кластеров
     *
     *
     * @param $replicas
     * @return bool
     */
    private function isReplicasWork($replicas)
    {
        $ok = true;
        if (!is_array($replicas)) {
            // @todo нет массива ошибка, т/к мы работем с репликами?
            // @todo Как быть есть в кластере НЕТ реплик ?
            return false;
        }
        foreach ($replicas as $replica) {
            if ($replica['is_readonly']) {
                $ok = false;
                $this->error[] = 'is_readonly : ' . json_encode($replica);
            }
            if ($replica['is_session_expired']) {
                $ok = false;
                $this->error[] = 'is_session_expired : ' . json_encode($replica);
            }
            if ($replica['future_parts'] > 20) {
                $ok = false;
                $this->error[] = 'future_parts : ' . json_encode($replica);
            }
            if ($replica['parts_to_check'] > 10) {
                $ok = false;
                $this->error[] = 'parts_to_check : ' . json_encode($replica);
            }

            // @todo : rewrite total_replicas=1 если кластер без реплики , нужно проверять какой класте и сколько в нем реплик
//            if ($replica['total_replicas']<2) {$ok=false;$this->error[]='total_replicas : '.json_encode($replica);}
            if ($this->softCheck )
            {
                if (!$ok) break;
                continue;
            }

            if ($replica['active_replicas'] < $replica['total_replicas']) {
                $ok = false;
                $this->error[] = 'active_replicas : ' . json_encode($replica);
            }
            if ($replica['queue_size'] > 20) {
                $ok = false;
                $this->error[] = 'queue_size : ' . json_encode($replica);
            }
            if (($replica['log_max_index'] - $replica['log_pointer']) > 10) {
                $ok = false;
                $this->error[] = 'log_max_index : ' . json_encode($replica);
            }
            if (!$ok) break;
        }
        return $ok;
    }

    private function getSelectSystemReplicas()
    {
        //  Если запрашивать все столбцы, то таблица может работать слегка медленно, так как на каждую строчку делается несколько чтений из ZK.
        //  Если не запрашивать последние 4 столбца (log_max_index, log_pointer, total_replicas, active_replicas), то таблица работает быстро.
        if ($this->softCheck)
        {

            return 'SELECT 
            database,table,engine,is_leader,is_readonly,
            is_session_expired,future_parts,parts_to_check,zookeeper_path,replica_name,replica_path,columns_version,
            queue_size,inserts_in_queue,merges_in_queue,queue_oldest_time,inserts_oldest_time,merges_oldest_time			
            FROM system.replicas
        ';
        }
        return 'SELECT * FROM system.replicas';
    }
    /**
     * @return $this
     */
    public function rescan()
    {
        $this->error = [];
        /*
         * 1) Получаем список IP
         * 2) К каждому подключаемся по IP, через activeClient подменяя host на ip
         * 3) Достаем информацию system.clusters + system.replicas c каждой машины , overwrite { DnsCache + timeOuts }
         * 4) Определяем нужные машины для кластера/реплики
         * 5) .... ?
         */
        $statementsReplicas = [];
        $statementsClusters = [];
        $result = [];

        $badNodes = [];
        $replicasIsOk = true;

        foreach ($this->nodes as $node) {
            $this->defaultClient()->setHost($node);




            $statementsReplicas[$node] = $this->defaultClient()->selectAsync($this->getSelectSystemReplicas());
            $statementsClusters[$node] = $this->defaultClient()->selectAsync('SELECT * FROM system.clusters');
            // пересетапим timeout
            $statementsReplicas[$node]->getRequest()->setDnsCache(0)->timeOut($this->scanTimeOut)->connectTimeOut($this->scanTimeOut);
            $statementsClusters[$node]->getRequest()->setDnsCache(0)->timeOut($this->scanTimeOut)->connectTimeOut($this->scanTimeOut);
        }
        $this->defaultClient()->executeAsync();
        $tables=[];

        foreach ($this->nodes as $node) {


            try {
                $r = $statementsReplicas[$node]->rows();
                foreach ($r as $row) {
                    $tables[$row['database']][$row['table']][$node] =$row;
                }
                $result['replicas'][$node] = $r;
            } catch (\Exception $E) {
                $result['replicas'][$node] = false;
                $badNodes[$node] = $E->getMessage();
                $this->error[] = 'statementsReplicas:' . $E->getMessage();
            }
            // ---------------------------------------------------------------------------------------------------
            $hosts=[];

            try {
                $c = $statementsClusters[$node]->rows();
                $result['clusters'][$node] = $c;
                foreach ($c as $row) {
                    $hosts[$row['host_address']][$row['port']] = $row['host_name'];
                    $result['cluster.list'][$row['cluster']][$row['host_address']] =
                        [
                            'shard_weight' => $row['shard_weight'],
                            'replica_num' => $row['replica_num'],
                            'shard_num' => $row['shard_num'],
                            'is_local' => $row['is_local']
                        ];
                }

            } catch (\Exception $E) {
                $result['clusters'][$node] = false;

                $this->error[] = 'clusters:' . $E->getMessage();
                $badNodes[$node] = $E->getMessage();

            }
            $this->hostsnames = $hosts;
            $this->tables = $tables;
            // ---------------------------------------------------------------------------------------------------
            // Проверим что репликации хорошо идут
            $rIsOk = $this->isReplicasWork($result['replicas'][$node]);
            $result['replicasIsOk'][$node] = $rIsOk;
            if (!$rIsOk) $replicasIsOk = false;
            // ---------------------------------------------------------------------------------------------------
        }

        // badNodes = array(6) {  '222.222.222.44' =>  string(13) "HttpCode:0 ; " , '222.222.222.11' =>  string(13) "HttpCode:0 ; "
        $this->badNodes = $badNodes;

        // Востановим DNS имя хоста в клиенте
        $this->defaultClient()->setHost($this->defaultHostName);


        $this->isScaned = true;
        $this->replicasIsOk = $replicasIsOk;
        $this->error[] = "Bad replicasIsOk, in " . json_encode($result['replicasIsOk']);
        // ------------------------------------------------
        // @todo Уточнить на боевых падениях и при разношорсных конфигурациях...
        if (sizeof($this->badNodes)) {
            $this->error[] = 'Have bad node : ' . json_encode($this->badNodes);
            $this->replicasIsOk = false;
        }
        if (!sizeof($this->error)) $this->error = false;
        $this->resultScan = $result;
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
    public function client($node)
    {
        // Создаем клиенты под каждый IP
        if (empty($this->clients[$node])) {
            $this->clients[$node] = clone $this->defaultClient();
        }

        $this->clients[$node]->setHost($node);

        return $this->clients[$node];
    }

    /**
     * @return Client
     */
    public function clientLike($cluster,$ip_addr_like)
    {
        $nodes_check=$this->nodes;
        $nodes=$this->getClusterNodes($cluster);
        $list_ips_need=explode(';',$ip_addr_like);
        $find=false;
        foreach($list_ips_need as $like)
        {
            foreach ($nodes as $node)
            {

                if (stripos($node,$like)!==false)
                {
                    if (in_array($node,$nodes_check))
                    {
                        $find=$node;
                    }
                    else
                    {
                        // node exists on cluster, but not check
                    }

                }
                if ($find) break;
            }
            if ($find) break;
        }
        if (!$find){
            $find=$nodes[0];
        }
        return $this->client($find);
    }
    /**
     * @return Client
     */
    public function activeClient()
    {
        return $this->client($this->nodes[0]);
    }

    /**
     * @param $cluster
     * @return int
     */
    public function getClusterCountShard($cluster)
    {
        $table = $this->getClusterInfoTable($cluster);
        $c = [];
        foreach ($table as $row) {
            $c[$row['shard_num']] = 1;
        }
        return sizeof($c);
    }

    /**
     * @param $cluster
     * @return int
     */
    public function getClusterCountReplica($cluster)
    {
        $table = $this->getClusterInfoTable($cluster);
        $c = [];
        foreach ($table as $row) {
            $c[$row['replica_num']] = 1;
        }
        return sizeof($c);
    }

    /**
     * @param $cluster
     * @return mixed
     */
    public function getClusterInfoTable($cluster)
    {
        $this->connect();
        if (empty($this->resultScan['cluster.list'][$cluster])) throw new QueryException('Cluster not find:' . $cluster);
        return $this->resultScan['cluster.list'][$cluster];
    }

    /**
     * @param $cluster
     * @return array
     */
    public function getClusterNodes($cluster)
    {
        return array_keys($this->getClusterInfoTable($cluster));
    }

    /**
     * @return array
     */
    public function getClusterList()
    {
        $this->connect();
        return array_keys($this->resultScan['cluster.list']);
    }

    /**
     * Список всех таблиц и бд. во всех кластерах
     *
     * @return array
     */
    public function getTables($resultDetail=false)
    {
        $this->connect();
        $list=[];
        foreach ($this->tables as $db_name=>$tables)
        {
            foreach ($tables as $table_name=>$nodes)
            {

                if ($resultDetail)
                {
                    $list[$db_name.'.'.$table_name]=$nodes;
                }
                else
                {
                    $list[$db_name.'.'.$table_name]=array_keys($nodes);
                }
            }
        }
        return $list;
    }

    /**
     * Размер таблицы в кластере
     *
     * @param $database_table
     * @return array
     *
     */
    public function getSizeTable($database_table)
    {
       $list=[];
       $nodes=$this->getNodesByTable($database_table);
        // scan need node`s
        foreach ($nodes as $node)
        {
            if (empty($this->_table_size_cache[$node]))
            {
                $this->_table_size_cache[$node]=$this->client($node)->tablesSize(true);
            }
        }

        $sizes=[];
        foreach ($this->_table_size_cache as $node=>$rows)
        {
            foreach ($rows as $row)
            {
                $sizes[$row['database'].'.'.$row['table']][$node]=$row;
                @$sizes[$row['database'].'.'.$row['table']]['total']['sizebytes']+=$row['sizebytes'];



            }
        }

        if (empty($sizes[$database_table]))
        {
            return null;
        }
        return $sizes[$database_table]['total']['sizebytes'];
    }


    /**
     * truncate
     *
     * @param $database_table
     * @return array
     */
    public function truncateTable($database_table,$timeOut=2000)
    {
        $out=[];
        list($db,$table)=explode('.',$database_table);
        $nodes=$this->getMasterNodeForTable($database_table);
        // scan need node`s
        foreach ($nodes as $node)
        {
            $def=$this->client($node)->getTimeout();
            $this->client($node)->database($db)->setTimeout($timeOut);
            $out[$node]=$this->client($node)->truncateTable($table);
            $this->client($node)->setTimeout($def);
        }
        return $out;
    }

    /**
     * is_leader ноды
     *
     * @param $database_table
     * @return array
     */
    public function getMasterNodeForTable($database_table)
    {
        $list=$this->getTables(true);

        if (empty($list[$database_table])) return [];


        $result=[];
        foreach ($list[$database_table] as $node=>$row)
        {
            if ($row['is_leader']) $result[]=$node;
        }
        return $result;
    }
    /**
     * Find nodes by : db_name.table_name
     *
     * @param $database_table
     * @return array
     */
    public function getNodesByTable($database_table)
    {
        $list=$this->getTables();
        if (empty($list[$database_table])) {
            throw new QueryException('Not find :' . $database_table);
        }
        return $list[$database_table];
    }

    /**
     * Error string
     *
     * @return string
     */
    public function getError()
    {
        if (is_array($this->error)) {
            return json_encode($this->error);
        }
        return $this->error;
    }

}
