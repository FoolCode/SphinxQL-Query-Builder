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
        $this->ensureConnection();

        $stm = $this->connection->prepare($query);

        try{
            $stm->execute();
        }
        catch(\PDOException $exception){
            throw new DatabaseException($exception->getMessage() . ' [' . $query . ']');
        }

        return ResultSet::make($stm);
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

        try {
            $con = new \Pdo($dsn);
        } catch (\PDOException $exception) {
            if (!$suppress_error && !$this->silence_connection_warning) {
                trigger_error('connection error', E_USER_WARNING);
            }

            throw new ConnectionException($exception->getMessage());
        }

        $this->connection = $con;
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return true;
    }

    public function ping()
    {
        $this->ensureConnection();

        return $this->connection !== null;
    }

    /**
     * @param array $queue
     * @return \Foolz\SphinxQL\Drivers\Pdo\MultiResultSet
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function multiQuery(array $queue)
    {
        $this->ensureConnection();

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

            return MultiResultSet::make($statement);
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
                    $set = ResultSet::make($statement);
                    $rowset = $set->getStored();
                } else {
                    $rowset = $statement->rowCount();
                }

                $result[$count] = $rowset;
                $count++;
            }

            return MultiResultSet::make($result);
        }
    }

    /**
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        $this->ensureConnection();

        return $this->connection->quote($value);
    }
}
