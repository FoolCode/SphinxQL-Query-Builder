<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;

/**
 * Class PdoConnection
 * @package Foolz\SphinxQL\Drivers
 */
class Connection extends ConnectionBase
{
    protected $silence_connection_warning = false;

    /**
     * @param bool $enable
     * @deprecated
     * not good
     */
    public function silenceConnectionWarning($enable = true)
    {
        $this->silence_connection_warning = $enable;
    }

    /**
     * close connection
     */
    public function close()
    {
        $this->connection = null;
    }

    /**
     * Performs a query on the Sphinx server.
     *
     * @param string $query The query string
     *
     * @throws DatabaseException
     * @return array|int The result array or number of rows affected
     */
    public function query($query)
    {
        $this->ping();

        $stm = $this->connection->prepare($query);

        try{
            $stm->execute();
        }
        catch(\PDOException $exception){
            throw new DatabaseException($exception->getMessage() . ' [' . $query . ']');
        }

        return new ResultSet($stm);
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function connect($suppress_error = false)
    {
        $params = $this->getParams();

        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        if (isset($params['socket']) && $params['socket'] != '') {
            $dsn .= 'unix_socket=' . $params['socket'] . ';';
        }

        if (!$suppress_error && ! $this->silence_connection_warning) {
            try {
                $con = new \Pdo($dsn);
            } catch (\PDOException $exception) {
                trigger_error('connection error', E_USER_WARNING);
            }
        } else {
            try {
                $con = new \Pdo($dsn);
            } catch (\PDOException $exception) {
                throw new ConnectionException($exception->getMessage());
            }
        }
        if(!isset($con))
        {
            throw new ConnectionException('connection error');
        }
        $this->connection = $con;
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return true;
    }

    public function ping()
    {
        try {
            $this->getConnection();
        } catch (ConnectionException $e) {
            $this->connect();
        }

        return $this->connection !== null;
    }

    /**
     * @param array $queue
     * @return array
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function multiQuery(Array $queue)
    {
        $this->ping();

        if (count($queue) === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        $result = array();
        $count = 0;

        if(version_compare(PHP_VERSION, '5.4.0', '>='))
        {
            try {
                $statement = $this->connection->query(implode(';', $queue));
            } catch (\PDOException $exception) {
                throw new DatabaseException($exception->getMessage() .' [ '.implode(';', $queue).']');
            }

            return new MultiResultSet($statement);
        }
        else
        {
            foreach($queue as $sql)
            {
                try {
                    $statement = $this->connection->query($sql);
                } catch (\PDOException $exception) {
                    throw new DatabaseException($exception->getMessage() .' [ '.implode(';', $queue).']');
                }
                if ($statement->columnCount()) {
                    $set = new ResultSet($statement);
                    $rowset = $set->getStored();
                } else {
                    $rowset = $statement->rowCount();
                }

                $result[$count] = $rowset;
                $count++;
            }

            return new MultiResultSet($result);
        }
    }

    /**
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        $this->ping();

        return $this->connection->quote($value);
    }
}
