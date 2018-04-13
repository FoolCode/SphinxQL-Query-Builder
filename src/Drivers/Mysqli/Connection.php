<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Drivers\ConnectionBase;

/**
 * SphinxQL connection class utilizing the MySQLi extension.
 * It also contains escaping and quoting functions.
 * @package Foolz\SphinxQL
 */
class Connection extends ConnectionBase
{
    /**
     * Internal Encoding
     *
     * @var string
     */
    protected $internal_encoding;

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
     * @inheritdoc
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
        $this->ensureConnection();
        return $this->getConnection()->ping();
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        $this->mbPop();
        $this->getConnection()->close();
        return parent::close();
    }

    /**
     * Performs a query on the Sphinx server.
     *
     * @param string $query The query string
     *
     * @return ResultSet The result array or number of rows affected
     * @throws DatabaseException If the executed query produced an error
     */
    public function query($query)
    {
        $this->ensureConnection();

        $resource = $this->getConnection()->query($query);

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.$query.']');
        }

        return new ResultSet($this, $resource);
    }

    /**
     * Performs multiple queries on the Sphinx server.
     *
     * @param array $queue Queue holding all of the queries to be executed
     *
     * @return MultiResultSet The result array
     * @throws DatabaseException In case a query throws an error
     * @throws SphinxQLException In case the array passed is empty
     */
    public function multiQuery(Array $queue)
    {
        $count = count($queue);

        if ($count === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        $this->ensureConnection();

        // HHVM bug (2015/07/07, HipHop VM 3.8.0-dev (rel)): $mysqli->error and $mysqli->errno aren't set
        if (!$this->getConnection()->multi_query(implode(';', $queue))) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.implode(';', $queue).']');
        };

        return new MultiResultSet($this);
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
        $this->ensureConnection();

        if (($value = $this->getConnection()->real_escape_string((string) $value)) === false) {
            // @codeCoverageIgnoreStart
            throw new DatabaseException($this->getConnection()->error, $this->getConnection()->errno);
            // @codeCoverageIgnoreEnd
        }

        return "'".$value."'";
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
