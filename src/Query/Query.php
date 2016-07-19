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
        $this->sql=$this->prepareQueryConditionsIfElse($this->sql,$this->bindings);
        return $this->sql;
    }
    private function prepareQueryConditionsIfElse($template,$markers)
    {
        //2. process if/else conditions
        $template = preg_replace_callback('#\{if\s(.+?)}(.+?)\{else}(.+?)\{/if}#sui', function($matches) use ($markers) {

            list($condition, $variable, $content_true,$content_false) = $matches;
            if(isset($markers[$variable]) && $markers[$variable])
                return $content_true;
            else
                return $content_false;
        }, $template);

        //3. process if conditions
        $template = preg_replace_callback('#\{if\s(.+?)}(.+?)\{/if}#sui', function($matches) use ($markers) {
            list($condition, $variable, $content) = $matches;
            if(isset($markers[$variable]) && $markers[$variable])
                return $content;
        }, $template);
        return $template;

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