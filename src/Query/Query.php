<?php
namespace ClickHouseDB;
class Query
{
    /**
     * @var
     */
    protected $sql;
    /**
     * @var array
     */
    protected $bindings = [];
    protected $format = null;
    public function __construct($sql,$bindings=[])
    {
        $this->sql=$sql;
        $this->bindings=$bindings;
    }
    public function setFormat($format)
    {
        $this->format=$format;
    }
    /**
     * @param array $bindings
     */
    public function bindParams(array $bindings)
    {
        foreach ($bindings as $column => $value) {
            $this->bindParam($column, $value);
        }
    }

    /**
     * @param string $column
     * @param mixed $value
     */
    public function bindParam($column, $value)
    {
        $this->bindings[$column] = $value;
    }

    /**
     * @return string
     */
    protected function prepareQueryBindings()
    {
        $keys = [];
        $values = [];
        foreach ($this->bindings as $key=>$value)
        {
            $valueSet=null;
            $valueSetText=null;
            if (null === $value || $value === false)
            {
                $valueSetText="";
            }
            if (is_array($value))
            {
                $valueSetText="'".implode("','", $value)."'";
                $valueSet=implode(", ", $value);
            }
            if (is_numeric($value))
            {
                $valueSetText=$value;
                $valueSet=$value;

            }
            if (is_string($value))
            {
                $valueSet=$value;
                $valueSetText="'".$value."'";
            }

            if ($valueSetText!==null)
            {
                $this->sql=str_ireplace(':'.$key,$valueSetText,$this->sql);
            }
            if ($valueSet!==null)
            {
                $this->sql=str_ireplace('{'.$key.'}',$valueSet,$this->sql);
            }
        }
        return $this->sql;
    }

    /**
     *
     * @return string
     */
    protected function prepareQueryFormat()
    {

        if (null !== $this->format) {
            $this->sql = $this->sql . ' FORMAT '.$this->format;
        }

        return $this->sql;
    }
    /**
     * @return string
     */
    public function toSql()
    {
        $this->prepareQueryBindings();
        $this->prepareQueryFormat();
        return $this->sql;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toSql();
    }

}