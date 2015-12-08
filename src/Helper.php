<?php

namespace Foolz\SphinxQL;
use Foolz\SphinxQL\Drivers\ConnectionInterface;

/**
 * SQL queries that don't require "query building"
 * These return a valid SphinxQL that can even be enqueued
 * @package Foolz\SphinxQL
 */
class Helper
{
    /**
     * @var ConnectionInterface
     */
    public $connection;

    protected function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param ConnectionInterface $connection
     *
     * @return Helper
     */
    public static function create(ConnectionInterface $connection)
    {
        return new static($connection);
    }

    /**
     * Returns a Connection object setup in the construct
     *
     * @return ConnectionInterface
     */
    protected function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns a new SphinxQL instance
     *
     * @return SphinxQL
     */
    protected function getSphinxQL()
    {
        return SphinxQL::create($this->getConnection());
    }

    /**
     * Prepares a query in SphinxQL (not executed)
     *
     * @param $sql
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    protected function query($sql)
    {
        return $this->getSphinxQL()->query($sql);
    }

    /**
     * Converts the columns from queries like SHOW VARIABLES to simpler key-value
     *
     * @param array $result The result of an executed query
     *
     * @return array Associative array with Variable_name as key and Value as value
     */
    public static function pairsToAssoc($result)
    {
        $ordered = array();

        foreach ($result as $item) {
            $ordered[$item['Variable_name']] = $item['Value'];
        }

        return $ordered;
    }

    /**
     * Runs query: SHOW META
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function showMeta()
    {
        return $this->query('SHOW META');
    }

    /**
     * Runs query: SHOW WARNINGS
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function showWarnings()
    {
        return $this->query('SHOW WARNINGS');
    }

    /**
     * Runs query: SHOW STATUS
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function showStatus()
    {
        return $this->query('SHOW STATUS');
    }

    /**
     * Runs query: SHOW TABLES
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function showTables()
    {
        return $this->query('SHOW TABLES');
    }

    /**
     * Runs query: SHOW VARIABLES
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function showVariables()
    {
        return $this->query('SHOW VARIABLES');
    }

    /**
     * SET syntax
     *
     * @param string  $name   The name of the variable
     * @param mixed   $value  The value of the variable
     * @param boolean $global True if the variable should be global, false otherwise
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function setVariable($name, $value, $global = false)
    {
        $query = 'SET ';

        if ($global) {
            $query .= 'GLOBAL ';
        }

        $user_var = strpos($name, '@') === 0;

        // if it has an @ it's a user variable and we can't wrap it
        if ($user_var) {
            $query .= $name.' ';
        } else {
            $query .= $this->getConnection()->quoteIdentifier($name).' ';
        }

        // user variables must always be processed as arrays
        if ($user_var && ! is_array($value)) {
            $query .= '= ('.$this->getConnection()->quote($value).')';
        } elseif (is_array($value)) {
            $query .= '= ('.implode(', ', $this->getConnection()->quoteArr($value)).')';
        } else {
            $query .= '= '.$this->getConnection()->quote($value);
        }

        return $this->query($query);
    }

    /**
     * CALL SNIPPETS syntax
     *
     * @param string $data    The document text (or documents) to search
     * @param string $index
     * @param string $query   Search query used for highlighting
     * @param array  $options Associative array of additional options
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function callSnippets($data, $index, $query, $options = array())
    {
        array_unshift($options, $data, $index, $query);

        $arr = $this->getConnection()->quoteArr($options);
        foreach ($arr as $key => &$val) {
            if (is_string($key)) {
                $val .= ' AS '.$key;
            }
        }

        return $this->query('CALL SNIPPETS('.implode(', ', $arr).')');
    }

    /**
     * CALL KEYWORDS syntax
     *
     * @param string      $text
     * @param string      $index
     * @param null|string $hits
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function callKeywords($text, $index, $hits = null)
    {
        $arr = array($text, $index);
        if ($hits !== null) {
            $arr[] = $hits;
        }

        return $this->query('CALL KEYWORDS('.implode(', ', $this->getConnection()->quoteArr($arr)).')');
    }

    /**
     * DESCRIBE syntax
     *
     * @param string $index The name of the index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function describe($index)
    {
        return $this->query('DESCRIBE '.$this->getConnection()->quoteIdentifier($index));
    }

    /**
     * CREATE FUNCTION syntax
     *
     * @param string $udf_name
     * @param string $returns  Whether INT|BIGINT|FLOAT|STRING
     * @param string $so_name
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function createFunction($udf_name, $returns, $so_name)
    {
        return $this->query('CREATE FUNCTION '.$this->getConnection()->quoteIdentifier($udf_name).
            ' RETURNS '.$returns.' SONAME '.$this->getConnection()->quote($so_name));
    }

    /**
     * DROP FUNCTION syntax
     *
     * @param string $udf_name
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function dropFunction($udf_name)
    {
        return $this->query('DROP FUNCTION '.$this->getConnection()->quoteIdentifier($udf_name));
    }

    /**
     * ATTACH INDEX * TO RTINDEX * syntax
     *
     * @param string $disk_index
     * @param string $rt_index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function attachIndex($disk_index, $rt_index)
    {
        return $this->query('ATTACH INDEX '.$this->getConnection()->quoteIdentifier($disk_index).
            ' TO RTINDEX '. $this->getConnection()->quoteIdentifier($rt_index));
    }

    /**
     * FLUSH RTINDEX syntax
     *
     * @param string $index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function flushRtIndex($index)
    {
        return $this->query('FLUSH RTINDEX '.$this->getConnection()->quoteIdentifier($index));
    }

    /**
     * OPTIMIZE INDEX syntax
     *
     * @param string $index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function optimizeIndex($index)
    {
        return $this->query('OPTIMIZE INDEX '.$this->getConnection()->quoteIdentifier($index));
    }

    /**
     * SHOW INDEX STATUS syntax
     *
     * @param $index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function showIndexStatus($index)
    {
        return $this->query('SHOW INDEX '.$this->getConnection()->quoteIdentifier($index).' STATUS');
    }

    /**
     * FLUSH RAMCHUNK syntax
     *
     * @param $index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function flushRamchunk($index)
    {
        return $this->query('FLUSH RAMCHUNK '.$this->getConnection()->quoteIdentifier($index));
    }
}
