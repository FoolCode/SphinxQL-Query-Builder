<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Drivers\ResultSetBase;

class ResultSet extends ResultSetBase
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var \mysqli_result
     */
    protected $result;

    /**
     * @param ResultSetAdapter $adapter
     * @param Connection $connection
     * @param null|\mysqli_result $result
     */
    public function __construct(ResultSetAdapter $adapter, Connection $connection, $result = null)
    {
        $this->connection = $connection;
        $this->adapter = $adapter;
        $this->result = $result;
        $this->init();
    }

    /**
     * @param Connection $connection
     * @param null|\mysqli_result $result
     * @return ResultSet
     */
    public static function make(Connection $connection, $result = null)
    {
        $adapter = new ResultSetAdapter($connection, $result);
        return new ResultSet($adapter, $connection, $result);
    }

    /**
     * Get the result object returned by PHP's MySQLi
     *
     * @return \mysqli_result
     *
     * @codeCoverageIgnore
     */
    public function getResultObject()
    {
        return $this->result;
    }

    /**
     * Get the MySQLi connection wrapper object
     *
     * @return Connection
     *
     * @codeCoverageIgnore
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the PHP MySQLi object
     *
     * @return \mysqli
     * @throws ConnectionException
     */
    public function getMysqliConnection()
    {
        return $this->connection->getConnection();
    }
}
