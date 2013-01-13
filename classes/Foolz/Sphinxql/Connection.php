<?php

namespace Foolz\SphinxQL;

class ConnectionException extends \Exception {};
class DatabaseException extends \Exception {};

/**
 * SphinxQL connection class utilizing the MySQLi extension.
 * Contains also escaping and quoting functions.
 */
class Connection
{
	/**
	 * The connection for this object
	 *
	 * @var  \Foolz\Sphinxql\Connection[]
	 */
	protected $connection = null;

	/**
	 * Disable warnings coming from server downtimes with a @ on \MySQLi
	 *
	 * @var  boolean
	 */
	protected $silence_connection_warning = false;

	/**
	 * Connection data array
	 *
	 * @var  array
	 */
	protected $connection_params = array('host' => '127.0.0.1', 'port' => 9306);

	/**
	 * While horrible, we have a function to enable silencing \MySQLi connection errors
	 * Use it only if you are running with high error reporting on a production server
	 *
	 * @param  boolean  $enable  True if it should be enabled, false if it should be disabled
	 */
	public function silenceConnectionWarning($enable = true)
	{
		$this->silence_connection_warning = $enable;
	}

	/**
	 * * Add a connection to the array
	 *
	 * @param  string  $name     The key name of the connection
	 * @param  string  $host     The hostname or IP
	 * @param  int     $port     The port to the host
	 */
	public function setConnectionParams($host = '127.0.0.1', $port = 9306)
	{
		$this->connection_params = array('host' => $host, 'port' => $port);

		return $this;
	}

	/**
	 * Returns the connection info (host, port, charset) for the currently selected connection
	 *
	 * @param  null|string  $name  The connection name or null for the currently active connection
	 *
	 * @return  array  The connection info
	 */
	public function getConnectionParams()
	{
		return $this->connection_params;
	}

	/**
	 * Returns the \MySQLi connection
	 *
	 * @return  \MySQLi  The MySQLi connection
	 * @throws  \Foolz\Sphinxql\ConnectionException  If there was no connection open or selected
	 */
	public function getConnection()
	{
		if ($this->connection !== null)
		{
			return $this->connection;
		}

		throw new ConnectionException('The connection has not yet been established.');
	}

	/**
	 * Enstablishes connection to SphinxQL with MySQLi
	 *
	 * @param  boolean  $suppress_error  If the warning on connection should be suppressed
	 *
	 * @return  boolean  True if connected
	 * @throws  \Foolz\Sphinxql\ConnectionException  If there was a connection error
	 */
	public function connect($suppress_error = false)
	{
		$data = $this->getConnectionParams();

		if ( ! $suppress_error && ! $this->silence_connection_warning)
		{
			$conn = new \MySQLi($data['host'], null, null, null, $data['port'], null);
		}
		else
		{
			$conn = @ new \MySQLi($data['host'], null, null, null, $data['port'], null);
		}

		if ($conn->connect_error)
		{
			throw new ConnectionException('Connection error: ['.$conn->connect_errno.']'
				.$conn->connect_error);
		}

		$this->connection = $conn;

		return true;
	}

	/**
	 * Ping the SphinxQL server
	 *
	 * @return  boolean  True if connected, false otherwise
	 */
	public function ping()
	{
		try
		{
			$this->getConnection();
		}
		catch (ConnectionException $e)
		{
			$this->connect();
		}

		return $this->getConnection()->ping();
	}

	/**
	 * Closes the connection to SphinxQL
	 */
	public function close()
	{
		$this->getConnection()->close();
		$this->connection = null;
	}

	/**
	 * Sends the query to Sphinx
	 *
	 * @param  string  $query  The query string
	 *
	 * @return  array  The result array
	 * @throws  \Foolz\Sphinxql\DatabaseException  If the executed query produced an error
	 */
	public function query($query)
	{
		try
		{
			$this->getConnection();
		}
		catch (ConnectionException $e)
		{
			$this->connect();
		}

		$resource = $this->getConnection()->query($query);

		if ($this->getConnection()->error)
		{
			throw new DatabaseException('['.$this->getConnection()->errno.'] '.
				$this->getConnection()->error.' [ '.$query.']');
		}

		if ($resource instanceof \mysqli_result)
		{
			$rows = array();
			while ($row = $resource->fetch_assoc())
			{
				$rows[] = $row;
			}

			$resource->free_result();

			return $rows;
		}

		// sphinxql doesn't return insert_id because we always have to point it out ourselves!
		return array($this->getConnection()->affected_rows);
	}

	/**
	 * Escapes the input with real_escape_string
	 * Taken from FuelPHP and edited
	 *
	 * @param  string  $value  The string to escape
	 *
	 * @return  string  The escaped string
	 * @throws  \Foolz\Sphinxql\DatabaseException  If there was an error during the escaping
	 */
	public function escape($value)
	{
		try
		{
			$this->getConnection();
		}
		catch (ConnectionException $e)
		{
			$this->connect();
		}

		if (($value = $this->getConnection()->real_escape_string((string) $value)) === false)
		{
			throw new DatabaseException($this->getConnection()->error, $this->getConnection()->errno);
		}

		return "'".$value."'";
	}

	/**
	 * Wraps the input in identifiers where necessary
	 *
	 * @param  \Foolz\Sphinxql\Expression|string  $value  The string to be quoted, or an Expression to leave it untouched
	 *
	 * @return  \Foolz\Sphinxql\Expression|string  The untouched Expression or the quoted string
	 */
	public function quoteIdentifier($value)
	{
		if ($value instanceof \Foolz\Sphinxql\Expression)
		{
			return $value->value();
		}

		if ($value === '*')
		{
			return $value;
		}

		$pieces = explode('.', $value);

		foreach ($pieces as $key => $piece)
		{
			$pieces[$key] = '`'.$piece.'`';
		}

		return implode('.', $pieces);
	}

	/**
	 * Runs $this->quoteIdentifier on every element of an array
	 *
	 * @param  array  $array  An array of strings to be quoted
	 *
	 * @return  array  The array of quoted strings
	 */
	public function quoteIdentifierArr(Array $array = array())
	{
		$result = array();

		foreach ($array as $key => $item)
		{
			$result[$key] = $this->quoteIdentifier($item);
		}

		return $result;
	}

	/**
	 * Adds quotes where necessary for values
	 * Taken from FuelPHP and edited
	 *
	 * @param  \Foolz\Sphinxql\Expression|string  $value  The input string, eventually wrapped in an expression to leave it untouched
	 *
	 * @return  \Foolz\Sphinxql\Expression|string  The untouched Expression or the quoted string
	 */
	public function quote($value)
	{
		if ($value === null)
		{
			return 'null';
		}
		elseif ($value === true)
		{
			return "'1'";
		}
		elseif ($value === false)
		{
			return "'0'";
		}
		elseif ($value instanceof Expression)
		{
			// Use a raw expression
			return $value->value();
		}
		elseif (is_int($value))
		{
			return (int) $value;
		}
		elseif (is_float($value))
		{
			// Convert to non-locale aware float to prevent possible commas
			return sprintf('%F', $value);
		}
		elseif (is_array($value))
		{
			// (1, 2, 3) format to support MVA attributes
			return '(' . implode(',', $this->quoteArr($value)) . ')';
		}

		return $this->escape($value);
	}

	/**
	 * Runs $this->quote() on every element of an array
	 *
	 * @param  array  $array  The array of strings to quote
	 *
	 * @return  array  The array of quotes strings
	 */
	public function quoteArr(Array $array = array())
	{
		$result = array();

		foreach ($array as $key => $item)
		{
			$result[$key] = $this->quote($item);
		}

		return $result;
	}
}