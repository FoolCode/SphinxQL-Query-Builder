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
class Connection
{
    /**
     * The \MySQLi connection for this object.
     *
     * @var \MySQLi
     */
    protected $connection = null;

    /**
     * Disables any warning outputs returned on the \MySQLi connection with @ prefix.
     *
     * @var boolean
     */
    protected $silence_connection_warning = false;

    /**
     * The connection parameters for the database server.
     *
     * @var array
     */
    protected $connection_params = array('host' => '127.0.0.1', 'port' => 9306);

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
     * Sets the connection parameters.
     *
     * @param string $host The hostname or IP
     * @param int $port The port to the host
     */
    public function setConnectionParams($host = '127.0.0.1', $port = 9306)
    {
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $this->connection_params = array('host' => $host, 'port' => $port);
    }

    /**
     * Returns the connection parameters (host, port) for the current instance.
     *
     * @return array The current connection parameters
     */
    public function getConnectionParams()
    {
        return $this->connection_params;
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
        $data = $this->getConnectionParams();

        if ( ! $suppress_error && ! $this->silence_connection_warning) {
            $conn = new \MySQLi($data['host'], null, null, null, $data['port'], null);
        } else {
            $conn = @ new \MySQLi($data['host'], null, null, null, $data['port'], null);
        }

        if ($conn->connect_error)
        {
            throw new ConnectionException('Connection Error: ['.$conn->connect_errno.']'
                .$conn->connect_error);
        }

        $this->connection = $conn;

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
        $this->getConnection()->close();
        $this->connection = null;
    }

    /**
     * Performs a query on the Sphinx server.
     *
     * @param string $query The query string
     *
     * @return array|int The result array or number of rows affected
     * @throws \Foolz\SphinxQL\DatabaseException If the executed query procduced an error
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
}
