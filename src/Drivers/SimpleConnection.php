<?php

namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;

/**
 * SphinxQL connection class utilizing the MySQLi extension.
 * It also contains escaping and quoting functions.
 * @package Foolz\SphinxQL
 */
class SimpleConnection extends ConnectionBase
{
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
     * Establishes a connection to the Sphinx server with \MySQLi.
     *
     * @param boolean $suppress_error If the warnings on the connection should be suppressed
     *
     * @return boolean True if connected
     * @throws ConnectionException If a connection error was encountered
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
     * @throws DatabaseException If the executed query produced an error
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
     * @throws DatabaseException In case a query throws an error
     * @throws SphinxQLException In case the array passed is empty
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
     * @throws DatabaseException If an error was encountered during server-side escape
     */
    public function escape($value)
    {
        $this->ping();

        if (($value = $this->getConnection()->real_escape_string((string) $value)) === false) {
            throw new DatabaseException($this->getConnection()->error, $this->getConnection()->errno);
        }

        return "'".$value."'";
    }
}
