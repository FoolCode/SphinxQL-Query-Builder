<?php

namespace Foolz\SphinxQL;

class ConnectionException extends \Exception {};
class DatabaseException extends \Exception {};
class SphinxQLException extends \Exception {};

/**
 * SphinxQL connection class utilizing the MySQLi extension.
 * It also contains escaping and quoting functions.
 * @package Foolz\SphinxQL
 */
class Connection implements ConnectionInterface
{
    /**
     * The \MySQLi connection for this object.
     *
     * @var \MySQLi
     */
    protected $connection = null;

    /**
     * The connection parameters for the database server.
     *
     * @var array
     */
    protected $connection_params = array('host' => '127.0.0.1', 'port' => 9306, 'socket' => null);

    /**
     * Internal Encoding
     *
     * @var string
     */
    protected $internal_encoding = null;

    /**
     * Disables any warning outputs returned on the \MySQLi connection with @ prefix.
     *
     * @var boolean
     */
    protected $silence_connection_warning = false;

    /**
     * Forces the \MySQLi connection to suppress all errors returned. This should only be used
     * when the production server is running with high error reporting settings.
     *
     * @param boolean $enable True if it should be enabled, false if it should be disabled
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
     * * string host - The hostname, IP address, or unix socket
     * * int port - The port to the host
     * * array options - MySQLi options/values, as an associative array. Example: array(MYSQLI_OPT_CONNECT_TIMEOUT => 2)
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
     * Sets the connection parameters.
     *
     * @param string $host The hostname or IP
     * @param int $port The port to the host
     * @deprecated Use ::setParams(array $params) or ::setParam($param, $value) instead. (deprecated August 2014)
     */
    public function setConnectionParams($host = '127.0.0.1', $port = 9306)
    {
        $this->setParam('host', $host);
        $this->setParam('port', $port);
    }

    /**
     * Returns the connection parameters (host, port, connection timeout) for the current instance.
     *
     * @return array $params The current connection parameters
     */
    public function getParams()
    {
        return $this->connection_params;
    }

    /**
     * Returns the internal encoding.
     *
     * @return string current multibyte internal encoding
     */
    public function getInternalEncoding()
    {
        return $this->internal_encoding;
    }

    /**
     * Returns the connection parameters (host, port) for the current instance.
     *
     * @return array The current connection parameters
     * @deprecated Use ::getParams() instead. (deprecated August 2014)
     */
    public function getConnectionParams()
    {
        return $this->getParams();
    }

    /**
     * Returns the current \MySQLi connection established.
     *
     *
     * @return \MySQLi MySQLi connection
     * @throws \Foolz\SphinxQL\ConnectionException If no connection has been established or open
     */
    public function getConnection()
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        throw new ConnectionException('The connection to the server has not been established yet.');
    }

    /**
     * Establishes a connection to the Sphinx server with \MySQLi.
     *
     * @param boolean $suppress_error If the warnings on the connection should be suppressed
     *
     * @return boolean True if connected
     * @throws \Foolz\SphinxQL\ConnectionException If a connection error was encountered
     */
    public function connect($suppress_error = false)
    {
        $data = $this->getParams();
        $conn = mysqli_init();

        if (!empty($data['options'])) {
            foreach ($data['options'] as $option => $value) {
                $conn->options($option, $value);
            }
        }

        if (!$suppress_error && ! $this->silence_connection_warning) {
            $conn->real_connect($data['host'], null, null, null, (int) $data['port'], $data['socket']);
        } else {
            @ $conn->real_connect($data['host'], null, null, null, (int) $data['port'], $data['socket']);
        }

        if ($conn->connect_error) {
            throw new ConnectionException('Connection Error: ['.$conn->connect_errno.']'
                .$conn->connect_error);
        }

        $conn->set_charset('utf8');
        $this->connection = $conn;
        $this->mbPush();

        return true;
    }

    /**
     * Pings the Sphinx server.
     *
     * @return boolean True if connected, false otherwise
     */
    public function ping()
    {
        try {
            $this->getConnection();
        } catch (ConnectionException $e) {
            $this->connect();
        }

        return $this->getConnection()->ping();
    }

    /**
     * Closes and unset the connection to the Sphinx server.
     */
    public function close()
    {
        $this->mbPop();
        $this->getConnection()->close();
        $this->connection = null;
    }

    /**
     * Performs a query on the Sphinx server.
     *
     * @param string $query The query string
     *
     * @return array|int The result array or number of rows affected
     * @throws \Foolz\SphinxQL\DatabaseException If the executed query produced an error
     */
    public function query($query)
    {
        $this->ping();

        $resource = $this->getConnection()->query($query);

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.$query.']');
        }

        if ($resource instanceof \mysqli_result) {
            $result = array();

            while ($row = $resource->fetch_assoc()) {
                $result[] = $row;
            }

            $resource->free_result();

            return $result;
        }

        // Sphinx doesn't return insert_id and only the number of rows affected.
        return $this->getConnection()->affected_rows;
    }

    /**
     * Performs multiple queries on the Sphinx server.
     *
     * @param array $queue Queue holding all of the queries to be executed
     *
     * @return array The result array
     * @throws \Foolz\SphinxQL\DatabaseException In case a query throws an error
     * @throws \Foolz\SphinxQL\SphinxQLException In case the array passed is empty
     */
    public function multiQuery(Array $queue)
    {
        if (count($queue) === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        $this->ping();

        $this->getConnection()->multi_query(implode(';', $queue));

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.implode(';', $queue).']');
        }

        $result = array();
        $count = 0;

        do {
            if ($resource = $this->getConnection()->store_result()) {
                $result[$count] = array();

                while ($row = $resource->fetch_assoc()) {
                    $result[$count][] = $row;
                }

                $resource->free_result();
            }

            $continue = false;

            if ($this->getConnection()->more_results()) {
                $this->getConnection()->next_result();
                $continue = true;
                $count++;
            }
        } while ($continue);

        return $result;
    }

    /**
     * Escapes the input with \MySQLi::real_escape_string.
     * Based on FuelPHP's escaping function.
     *
     * @param string $value The string to escape
     *
     * @return string The escaped string
     * @throws \Foolz\SphinxQL\DatabaseException If an error was encountered during server-side escape
     */
    public function escape($value)
    {
        $this->ping();

        if (($value = $this->getConnection()->real_escape_string((string) $value)) === false) {
            throw new DatabaseException($this->getConnection()->error, $this->getConnection()->errno);
        }

        return "'".$value."'";
    }

    /**
     * Wraps the input with identifiers when necessary.
     *
     * @param \Foolz\SphinxQL\Expression|string $value The string to be quoted, or an Expression to leave it untouched
     *
     * @return \Foolz\SphinxQL\Expression|string The untouched Expression or the quoted string
     */
    public function quoteIdentifier($value)
    {
        if ($value instanceof \Foolz\SphinxQL\Expression) {
            return $value->value();
        }

        if ($value === '*') {
            return $value;
        }

        $pieces = explode('.', $value);

        foreach ($pieces as $key => $piece) {
            $pieces[$key] = '`'.$piece.'`';
        }

        return implode('.', $pieces);
    }

    /**
     * Calls $this->quoteIdentifier() on every element of the array passed.
     *
     * @param array $array An array of strings to be quoted
     *
     * @return array The array of quoted strings
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
     * Adds quotes around values when necessary.
     * Based on FuelPHP's quoting function.
     *
     * @param \Foolz\SphinxQL\Expression|string $value The input string, eventually wrapped in an expression to leave it untouched
     *
     * @return \Foolz\SphinxQL\Expression|string The untouched Expression or the quoted string
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
            return (int) $value;
        } elseif (is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return sprintf('%F', $value);
        }  elseif (is_array($value)) {
            // Supports MVA attributes
            return '('.implode(',', $this->quoteArr($value)).')';
        }

        return $this->escape($value);
    }

    /**
     * Calls $this->quote() on every element of the array passed.
     *
     * @param array $array The array of strings to quote
     *
     * @return array The array of quotes strings
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
     * Enter UTF-8 multi-byte workaround mode.
     */
    public function mbPush()
    {
        $this->internal_encoding = mb_internal_encoding();
        mb_internal_encoding('UTF-8');

        return $this;
    }

    /**
     * Exit UTF-8 multi-byte workaround mode.
     */
    public function mbPop()
    {
        mb_internal_encoding($this->internal_encoding);
        $this->internal_encoding = null;

        return $this;
    }
}
