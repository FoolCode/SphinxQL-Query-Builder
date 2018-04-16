<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Drivers\ResultSetBase;
use Foolz\SphinxQL\Exception\ConnectionException;
use mysqli;
use mysqli_result;

class ResultSet extends ResultSetBase
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var mysqli_result|bool
     */
    protected $result;

    /**
     * @param Connection         $connection
     * @param mysqli_result|bool $result
     */
    public function __construct(Connection $connection, $result)
    {
        $this->connection = $connection;
        $this->adapter = new ResultSetAdapter($connection, $result);
        $this->result = $result;
        $this->init();
    }

    /**
     * Get the result object returned by PHP's MySQLi
     *
     * @return mysqli_result
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
     * @return mysqli
     * @throws ConnectionException
     */
    public function getMysqliConnection()
    {
        return $this->connection->getConnection();
    }
}
