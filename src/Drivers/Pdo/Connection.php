<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use PDO;
use PDOException;

class Connection extends ConnectionBase
{
    /**
     * @inheritdoc
     */
    public function query($query)
    {
        $this->ensureConnection();

        $stm = $this->connection->prepare($query);

        try {
            $stm->execute();
        } catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage() . ' [' . $query . ']');
        }

        return new ResultSet($stm);
    }

    /**
     * @inheritdoc
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
            $con = new PDO($dsn);
        } catch (PDOException $exception) {
            if (!$suppress_error && !$this->silence_connection_warning) {
                trigger_error('connection error', E_USER_WARNING);
            }

            throw new ConnectionException($exception->getMessage());
        }

        $this->connection = $con;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return true;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function ping()
    {
        $this->ensureConnection();

        return $this->connection !== null;
    }

    /**
     * @inheritdoc
     */
    public function multiQuery(array $queue)
    {
        $this->ensureConnection();

        if (count($queue) === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        try {
            $statement = $this->connection->query(implode(';', $queue));
        } catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage() .' [ '.implode(';', $queue).']');
        }

        return new MultiResultSet($statement);
    }

    /**
     * @inheritdoc
     */
    public function escape($value)
    {
        $this->ensureConnection();

        return $this->connection->quote($value);
    }
}
