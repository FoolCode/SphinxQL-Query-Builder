<?php
namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Expression;

abstract class ConnectionBase implements ConnectionInterface
{
    /**
     * The connection parameters for the database server.
     *
     * @var array
     */
    protected $connection_params = array('host' => '127.0.0.1', 'port' => 9306, 'socket' => null);

    /**
     * Internal connection object.
     */
    protected $connection;

    /**
     * Disables any warning outputs returned on the connection with @ prefix.
     *
     * @var boolean
     */
    protected $silence_connection_warning = false;

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
     * Returns the connection parameters (host, port, connection timeout) for the current instance.
     *
     * @return array $params The current connection parameters
     */
    public function getParams()
    {
        return $this->connection_params;
    }

    /**
     * Returns the current connection established.
     *
     * @return object Internal connection object
     * @throws ConnectionException If no connection has been established or open
     */
    public function getConnection()
    {
        if (!is_null($this->connection)) {
            return $this->connection;
        }

        throw new ConnectionException('The connection to the server has not been established yet.');
    }

    /**
     * Adds quotes around values when necessary.
     * Based on FuelPHP's quoting function.
     *
     * @param Expression|string|null|bool|array|int|float $value The input string, eventually wrapped in an expression
     *      to leave it untouched
     *
     * @return Expression|string|int The untouched Expression or the quoted string
     */
    public function quote($value)
    {
        if ($value === null) {
            return 'null';
        } elseif ($value === true) {
            return 1;
        } elseif ($value === false) {
            return 0;
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
     * Establishes a connection if needed
     * @throws ConnectionException
     */
    protected function ensureConnection()
    {
        try {
            $this->getConnection();
        } catch (ConnectionException $e) {
            $this->connect();
        }
    }

    /**
     * Establishes a connection to the Sphinx server.
     *
     * @param bool $suppress_error If the warnings on the connection should be suppressed
     *
     * @return bool True if connected
     * @throws ConnectionException If a connection error was encountered
     */
    abstract public function connect($suppress_error = false);

    /**
     * Forces the connection to suppress all errors returned. This should only be used
     * when the production server is running with high error reporting settings.
     *
     * @param boolean $enable True if it should be enabled, false if it should be disabled
     * @deprecated
     * not good
     */
    public function silenceConnectionWarning($enable = true)
    {
        $this->silence_connection_warning = $enable;
    }
}
