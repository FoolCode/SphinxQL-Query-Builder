<?php

namespace Foolz\SphinxQL;

use Foolz\SphinxQL\Drivers\ConnectionInterface;

/**
 * SQL queries that don't require "query building"
 * These return a valid SphinxQL that can even be enqueued
 */
class Helper
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns a new SphinxQL instance
     *
     * @return SphinxQL
     */
    protected function getSphinxQL()
    {
        return new SphinxQL($this->connection);
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
     * @todo make non static
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
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function showTables( $index )
    {
        $queryAppend = '';
        if ( ! empty( $index ) ) {
            $queryAppend = ' LIKE ' . $this->connection->quote($index);
        }
        return $this->query( 'SHOW TABLES' . $queryAppend );
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
     * @param string $name   The name of the variable
     * @param mixed  $value  The value of the variable
     * @param bool   $global True if the variable should be global, false otherwise
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function setVariable($name, $value, $global = false)
    {
        $query = 'SET ';

        if ($global) {
            $query .= 'GLOBAL ';
        }

        $user_var = strpos($name, '@') === 0;

        $query .= $name.' ';

        // user variables must always be processed as arrays
        if ($user_var && !is_array($value)) {
            $query .= '= ('.$this->connection->quote($value).')';
        } elseif (is_array($value)) {
            $query .= '= ('.implode(', ', $this->connection->quoteArr($value)).')';
        } else {
            $query .= '= '.$this->connection->quote($value);
        }

        return $this->query($query);
    }

    /**
     * CALL SNIPPETS syntax
     *
     * @param string|array $data    The document text (or documents) to search
     * @param string       $index
     * @param string       $query   Search query used for highlighting
     * @param array        $options Associative array of additional options
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function callSnippets($data, $index, $query, $options = array())
    {
        $documents = array();
        if (is_array($data)) {
            $documents[] = '('.implode(', ', $this->connection->quoteArr($data)).')';
        } else {
            $documents[] = $this->connection->quote($data);
        }

        array_unshift($options, $index, $query);

        $arr = $this->connection->quoteArr($options);
        foreach ($arr as $key => &$val) {
            if (is_string($key)) {
                $val .= ' AS '.$key;
            }
        }

        return $this->query('CALL SNIPPETS('.implode(', ', array_merge($documents, $arr)).')');
    }

    /**
     * CALL KEYWORDS syntax
     *
     * @param string      $text
     * @param string      $index
     * @param null|string $hits
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function callKeywords($text, $index, $hits = null)
    {
        $arr = array($text, $index);
        if ($hits !== null) {
            $arr[] = $hits;
        }

        return $this->query('CALL KEYWORDS('.implode(', ', $this->connection->quoteArr($arr)).')');
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
        return $this->query('DESCRIBE '.$index);
    }

    /**
     * CREATE FUNCTION syntax
     *
     * @param string $udf_name
     * @param string $returns  Whether INT|BIGINT|FLOAT|STRING
     * @param string $so_name
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function createFunction($udf_name, $returns, $so_name)
    {
        return $this->query('CREATE FUNCTION '.$udf_name.
            ' RETURNS '.$returns.' SONAME '.$this->connection->quote($so_name));
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
        return $this->query('DROP FUNCTION '.$udf_name);
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
        return $this->query('ATTACH INDEX '.$disk_index.' TO RTINDEX '.$rt_index);
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
        return $this->query('FLUSH RTINDEX '.$index);
    }

    /**
     * TRUNCATE RTINDEX syntax
     *
     * @param string $index
     *
     * @return SphinxQL A SphinxQL object ready to be ->execute();
     */
    public function truncateRtIndex($index)
    {
        return $this->query('TRUNCATE RTINDEX '.$index);
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
        return $this->query('OPTIMIZE INDEX '.$index);
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
        return $this->query('SHOW INDEX '.$index.' STATUS');
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
        return $this->query('FLUSH RAMCHUNK '.$index);
    }
}
