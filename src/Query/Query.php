<?php

namespace ClickHouseDB\Query;

use ClickHouseDB\Exception\QueryException;

class Query
{
    /**
     * @var string
     */
    protected $sql;

    /**
     * @var null
     */
    protected $format = null;

    /**
     * @var array
     */
    private $degenerations=[];

    /**
     * Query constructor.
     * @param $sql
     * @param array $degenerations
     */
    public function __construct($sql,$degenerations=[])
    {
        if (!trim($sql))
        {
            throw new QueryException('Empty Query');
        }
        $this->sql = $sql;
        $this->degenerations=$degenerations;
    }

    /**
     * @param $format
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }


    private function applyFormatQuery()
    {
        // FORMAT\s(\w)*$
        if (null === $this->format) return false;
        $supportFormats=
            "FORMAT\\s+TSV|FORMAT\\s+TSVRaw|FORMAT\\s+TSVWithNames|FORMAT\\s+TSVWithNamesAndTypes|FORMAT\\s+Vertical|FORMAT\\s+JSONCompact|FORMAT\\s+JSONEachRow|FORMAT\\s+TSKV|FORMAT\\s+TabSeparatedWithNames|FORMAT\\s+TabSeparatedWithNamesAndTypes|FORMAT\\s+TabSeparatedRaw|FORMAT\\s+BlockTabSeparated|FORMAT\\s+CSVWithNames|FORMAT\\s+CSV|FORMAT\\s+JSON|FORMAT\\s+TabSeparated";

        $matches=[];
        if (preg_match_all('%('.$supportFormats.')%ius',$this->sql,$matches)){

            // skip add "format json"
            if (isset($matches[0]))
            {
                $format=trim(str_ireplace('format','',$matches[0][0]));
                $this->format=$format;

            }
        }
        else {
            $this->sql = $this->sql . ' FORMAT ' . $this->format;
        }






    }
    public function getFormat()
    {

        return $this->format;
    }

    /**
     * @return string
     */
    public function toSql()
    {
        if (null !== $this->format) {
            $this->applyFormatQuery();
        }

        if (sizeof($this->degenerations))
        {
            foreach ($this->degenerations as $degeneration)
            {
                if ($degeneration instanceof \ClickHouseDB\Query\Degeneration)
                $this->sql=$degeneration->process($this->sql);
            }
        }

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
