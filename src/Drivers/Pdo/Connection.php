<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Expression;

/**
 * Class PdoConnection
 * @package Foolz\SphinxQL\Drivers
 */
class Connection implements ConnectionInterface
{

    /**
     * @var \Pdo
     */
    protected $connection = null;

    /**
     * The connection parameters for the database server.
     *
     * @var array
     */
    protected $connection_params = array('host' => '127.0.0.1', 'port' => 9306, 'socket' => null);


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
     * Sets one or more connection parameters.
     *
     * @param array $params Associative array of parameters and values.
     */
    public function setParams(Array $params)
    {
        foreach ($params as $param => $value) {
            $this->setParam($param, $value);
        }
    }

    /**
     * Set a single connection parameter. Valid parameters include:
     *
     * * string host - The hostname, IP address
     * * int port - The port to the host
     *
     * @param string $param Name of the parameter to modify.
     * @param mixed $value Value to which the parameter will be set.
     */
    public function setParam($param, $value)
    {
        if ($param === 'host') {
            if ($value === 'localhost') {
                $value = '127.0.0.1';
            } elseif (stripos($value, 'unix:') === 0) {
                $param = 'socket';
            }
        }
        if ($param === 'socket') {
            if (stripos($value, 'unix:') === 0) {
                $value = substr($value, 5);
            }
            $this->connection_params['host'] = null;
        }

        $this->connection_params[$param] = $value;
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

        return new ResultSet($this, $stm);
    }

    /**
     * @return \Pdo
     * @throws ConnectionException
     */
    public function getConnection()
    {
        if (!is_null($this->connection)) {
            return $this->connection;
        }

        throw new ConnectionException('The connection to the server has not been established yet.');
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
     * Returns the connection parameters (host, port) for the current instance.
     *
     * @return array $params The current connection parameters
     */
    public function getParams()
    {
        return $this->connection_params;
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

            return new MultiResultSet($this, $statement, count($queue));
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
                    $rowset = new ResultSet($this, $statement);
                } else {
                    $rowset = $statement->rowCount();
                }

                $result[$count] = $rowset;
                $count++;
            }

            return new MultiResultSet($this, $result, count($queue));
        }

        return $result;
    }

    /**
     * @param array $array
     * @return array
     */
    public function quoteIdentifierArr(Array $array = array())
    {
        $result = array();

        foreach ($array as $key => $item) {
            $result[$key] = $this->quoteIdentifier($item);
        }

        return $result;
    }

    /**
     * @param Expression|string $value
     * @return Expression|string
     */
    public function quoteIdentifier($value)
    {
        if ($value instanceof Expression) {
            return $value->value();
        }

        if ($value === '*') {
            return $value;
        }

        $pieces = explode('.', $value);

        foreach ($pieces as $key => $piece) {
            $pieces[$key] = '`' . $piece . '`';
        }

        return implode('.', $pieces);
    }

    /**
     * Adds quotes around values when necessary.
     * Based on FuelPHP's quoting function.
     *
     * @param Expression|string $value The input string, eventually wrapped in an expression to leave it untouched
     *
     * @return Expression|string|int The untouched Expression or the quoted string
     */
    public function quote($value)
    {
        if ($value === null) {
            return 'null';
        } elseif ($value === true) {
            return "'1'";
        } elseif ($value === false) {
            return "'0'";
        } elseif ($value instanceof Expression) {
            // Use the raw expression
            return $value->value();
        } elseif (is_int($value)) {
            return (int)$value;
        } elseif (is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return sprintf('%F', $value);
        } elseif (is_array($value)) {
            // Supports MVA attributes
            return '(' . implode(',', $this->quoteArr($value)) . ')';
        }

        return $this->escape($value);
    }

    /**
     * @param array $array
     * @return array
     */
    public function quoteArr(Array $array = array())
    {
        $result = array();

        foreach ($array as $key => $item) {
            $result[$key] = $this->quote($item);
        }

        return $result;
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
