<?php

namespace ClickHouseDB\Query;

use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Query\Degeneration\Bindings;
use ClickHouseDB\Query\Degeneration\Conditions;
use function sizeof;

class Query
{
    /**
     * @var string
     */
    protected $sql;

    /**
     * @var string
     */
    protected $originalSql;

    /**
     * @var string|null
     */
    protected $format = null;

    /**
     * @var array
     */
    private $degenerations = [];

    private $supportFormats=[
        "FORMAT\\s+TSVRaw",
        "FORMAT\\s+TSVWithNamesAndTypes",
        "FORMAT\\s+TSVWithNames",
        "FORMAT\\s+TSV",
        "FORMAT\\s+Vertical",
        "FORMAT\\s+JSONCompact",
        "FORMAT\\s+JSONEachRow",
        "FORMAT\\s+TSKV",
        "FORMAT\\s+TabSeparatedWithNames",
        "FORMAT\\s+TabSeparatedWithNamesAndTypes",
        "FORMAT\\s+TabSeparatedRaw",
        "FORMAT\\s+BlockTabSeparated",
        "FORMAT\\s+CSVWithNames",
        "FORMAT\\s+CSV",
        "FORMAT\\s+JSON",
        "FORMAT\\s+TabSeparated"
    ];

    /**
     * Query constructor.
     * @param string $sql
     * @param array $degenerations
     */
    public function __construct($sql, $degenerations = [])
    {
        if (!trim($sql))
        {
            throw new QueryException('Empty Query');
        }
        $this->sql = $this->originalSql = $sql;
        $this->degenerations = $degenerations;
    }

    /**
     * @param string|null $format
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }


    private function applyFormatQuery()
    {
        // FORMAT\s(\w)*$
        if (null === $this->format) {
            return false;
        }
        $supportFormats = implode("|",$this->supportFormats);

        $this->sql = trim($this->sql);
        if (substr($this->sql, -1) == ';') {
            $this->sql = substr($this->sql, 0, -1);
        }

        $matches = [];
        if (preg_match_all('%(' . $supportFormats . ')%ius', $this->sql, $matches)) {

            // skip add "format json"
            if (isset($matches[0]))
            {

                $this->format = trim(str_ireplace('format', '', $matches[0][0]));

            }
        } else {
            $this->sql = $this->sql . ' FORMAT ' . $this->format;
        }






    }

    /**
     * @return null|string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Check if the sql contains bindings like {p1:UInt8}.
     *
     * Check the original SQL before degeneration to prevent data that matches the same regex by accident causing adding bindings to the url
     * For backwards compatibility use the degenerated sql when custom degenerations are found
     */
    public function isUseInUrlBindingsParams():bool
    {
        //  'query=select {p1:UInt8} + {p2:UInt8}' -F "param_p1=3" -F "param_p2=4"
        return preg_match('#{[\w+]+:[\w+()]+}#', $this->hasCustomDegenerations() ? $this->sql : $this->originalSql);

    }
    public function getUrlBindingsParams():array
    {
        $out=[];
        $params=[];
        if (sizeof($this->degenerations)) {
            foreach ($this->degenerations as $degeneration) {
                if ($degeneration instanceof Degeneration) {
                    $params=$degeneration->getBind();
                    break;
                    // need first response
                }
            }
        }
        if (sizeof($params)) {
            foreach ($params as $key=>$value)
            {
                $out['param_'.$key]=$value;
            }
        }
        return $out;
    }

    public function toSql()
    {
        if ($this->format !== null) {
            $this->applyFormatQuery();
        }

        if (sizeof($this->degenerations))
        {
            foreach ($this->degenerations as $degeneration)
            {
                if ($degeneration instanceof Degeneration) {
                    $this->sql = $degeneration->process($this->sql);
                }
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

    private function hasCustomDegenerations(): bool
    {
        return count(array_filter($this->degenerations, function (Degeneration $degeneration) {
            return !in_array($degeneration::class, [Conditions::class, Bindings::class]);
        })) > 0;
    }
}
