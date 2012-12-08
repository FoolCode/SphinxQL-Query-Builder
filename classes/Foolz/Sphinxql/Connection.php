<?php

namespace Foolz\Sphinxql;

class SphinxqlConnectionException extends \Exception {};

/**
 * SphinxQL connection class based on MySQLi.
 * Contains also escaping and quoting functions.
 */
class Connection
{
	/**
	 * The array of live connections
	 *
	 * @var  \MySQLi[]
	 */
	protected static $connections = array();

	/**
	 * The array key of the current selected connection
	 *
	 * @var  string
	 */
	protected static $current_connection = 'default';

	/**
	 * Disable warnings coming from server downtimes with a @ on \MySQLi
	 *
	 * @var  boolean
	 */
	protected static $silence_connection_warning = false;

	/**
	 * Connection data array
	 *
	 * @var  array
	 */
	protected static $connection_info = array(
		'default' => array(
			'host' => '127.0.0.1',
			'port' => 9306
		)
	);

	/**
	 * Creates a new Sphinxql object and if necessary connects to DB
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  A new Sphinxql object
	 */
	public static function forge()
	{
		$new = new Sphinxql;

		try
		{
			static::getConnection();
		}
		catch (SphinxqlConnectionException $e)
		{
			static::connect();
		}

		return $new;
	}

	/**
	 * While horrible, we have a function to enable silencing \MySQLi connection errors
	 * Use it only if you are running with high error reporting on a production server
	 *
	 * @param  boolean  $enable  True if it should be enabled, false if it should be disabled
	 */
	public static function silenceConnectionWarning($enable = true)
	{
		static::$silence_connection_warning = $enable;
	}

	/**
	 * Add a connection to the array
	 *
	 * @param  string  $name     The key name of the connection
	 * @param  string  $host     The hostname or IP
	 * @param  int     $port     The port to the host
	 */
	public static function addConnection($name = 'default', $host = '127.0.0.1', $port = 9306)
	{
		if ($host === 'localhost')
		{
			$host = '127.0.0.1';
		}

		static::$connection_info[$name] = array('host' => $host, 'port' => $port);
	}

	/**
	 * Sets the connection to use
	 *
	 * @param  string  $name  The name of the connection
	 */
	public static function setConnection($name)
	{
		static::$current_connection = $name;
	}

	/**
	 * Returns the connection info (host, port, charset) for the currently selected connection
	 *
	 * @param  null|string  $name  The connection name or null for the currently active connection
	 *
	 * @return  array  The connection info
	 */
	public static function getConnectionInfo($name = null)
	{
		if ($name !== null)
		{
			return static::$connection_info[$name];
		}

		return static::$connection_info[static::$current_connection];
	}

	/**
	 * Enstablishes connection to SphinxQL with MySQLi
	 *
	 * @param  boolean  $suppress_error  If the warning on connection should be suppressed
	 *
	 * @return  boolean  True if connected
	 * @throws  \Foolz\Sphinxql\SphinxqlConnectionException  If there was a connection error
	 */
	public static function connect($suppress_error = false)
	{
		$data = static::getConnectionInfo();

		if ( ! $suppress_error && ! static::$silence_connection_warning)
		{
			static::$connections[static::$current_connection] =
				new \MySQLi($data['host'], null, null, null, $data['port'], null);
		}
		else
		{
			static::$connections[static::$current_connection] =
				@ new \MySQLi($data['host'], null, null, null, $data['port'], null);
		}

		if (static::getConnection()->connect_error)
		{
			throw new SphinxqlConnectionException('Connection error: ['.static::getConnection()->connect_errno.']'
				.static::getConnection()->connect_error);
		}

		return true;
	}

	/**
	 * Ping the SphinxQL server
	 *
	 * @return  boolean  True if connected, false otherwise
	 */
	public static function ping()
	{
		try
		{
			static::getConnection();
		}
		catch (SphinxqlConnectionException $e)
		{
			static::connect();
		}

		return static::getConnection()->ping();
	}

	/**
	 * Closes the connection to SphinxQL
	 */
	public static function close()
	{
		static::getConnection()->close();
		unset(static::$connections[static::$current_connection]);
	}

	/**
	 * Returns the \MySQLi connection
	 *
	 * @return  \MySQLi  The MySQLi connection
	 * @throws  \Foolz\Sphinxql\SphinxqlConnectionException  If there was no connection open or selected
	 */
	public static function getConnection()
	{
		if (isset(static::$connections[static::$current_connection]))
		{
			return static::$connections[static::$current_connection];
		}

		throw new SphinxqlConnectionException('The connection has not yet been established.');
	}

	/**
	 * Sends the query to Sphinx
	 *
	 * @param  string  $query  The query string
	 *
	 * @return  array  The result array
	 * @throws  \Foolz\Sphinxql\SphinxqlDatabaseException  If the executed query produced an error
	 */
	public static function query($query)
	{
		try
		{
			static::getConnection();
		}
		catch (SphinxqlConnectionException $e)
		{
			static::connect();
		}

		$resource = static::getConnection()->query($query);

		if (static::getConnection()->error)
		{
			throw new SphinxqlDatabaseException('['.static::getConnection()->errno.'] '.
				static::getConnection()->error.' [ '.$query.']');
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
		return array(static::getConnection()->affected_rows);
	}

	/**
	 * Escapes the input with real_escape_string
	 * Taken from FuelPHP and edited
	 *
	 * @param  string  $value  The string to escape
	 *
	 * @return  string  The escaped string
	 * @throws  \Foolz\Sphinxql\SphinxqlDatabaseException  If there was an error during the escaping
	 */
	public function escape($value)
	{
		try
		{
			static::getConnection();
		}
		catch (SphinxqlConnectionException $e)
		{
			static::connect();
		}

		if (($value = $this->getConnection()->real_escape_string((string) $value)) === false)
		{
			throw new SphinxqlDatabaseException($this->getConnection()->error, $this->getConnection()->errno);
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