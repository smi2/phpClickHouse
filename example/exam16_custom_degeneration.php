<?php


include_once __DIR__ . '/../include.php';
include_once __DIR__ . '/Helper.php';
\ClickHouseDB\Example\Helper::init();


class CustomDegeneration implements \ClickHouseDB\Query\Degeneration
{
    private $bindings=[];
    public function bindParams(array $bindings)
    {
        $this->bindings=$bindings;
    }
    public function process($sql)
    {
        if (sizeof($this->bindings))
        {
            foreach ($this->bindings as $key=>$value)
            {
                $sql=str_ireplace('%'.$key.'%',$value,$sql);
            }
        }
        return str_ireplace('XXXX','SELECT',$sql);
    }
}


$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);

print_r($db->select('SELECT 1 as ping')->fetchOne());



// CustomConditions
$db->addQueryDegeneration(new CustomDegeneration());


// strreplace XXXX=>SELECT
print_r($db->select('XXXX 1 as ping')->fetchOne());



// SELECT 1 as ping
print_r($db->select('XXXX 1 as %ZX%',['ZX'=>'ping'])->fetchOne());
