<?php
namespace Foolz\Sphinxql;

class SphinxqlConnectionException extends \Exception {};

/**
 * SphinxQL connection class based on MySQLi.
 * Contains also escaping and quoting functions.
 */
class SphinxqlConnection
{
	
	/**
	 * The array of live connections
	 * 
	 * @var object
	 */
	protected static $connections = array();
	
	/**
	 * The array key of the current selected connection
	 *
	 * @var string 
	 */
	protected static $current_connection = 'default';
	
	
	/**
	 * Disable warnings coming from server downtimes with a @ on \MySQLi
	 *
	 * @var string 
	 */
	protected static $silence_connection_warning = false;
	
	
	/**
	 * Connection data array
	 * 
	 * @var type 
	 */
	protected static $connection_info = array(
		'default' => array(
			'host' => '127.0.0.1',
			'port' => 9306,
			'charset' => 'utf8'
		)
	);
	
	
	/**
	 * Creates a new Sphinxql object and if necessary connects to DB
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public static function forge()
	{
		$new = new Sphinxql;
		
		static::getConnection() or static::connect();
		
		return $new;
	}
	
	
	/**
	 * While horrible, we have a function to enable silencing \MySQLi connection errors
	 * Use it only if you are running with high error reporting on a production server
	 * 
	 * @param bool $enable
	 */
	public static function silenceConnectionWarning($enable = true)
	{
		static::$silence_connection_warning = $enable;
	}

	
	/**
	 * Add a connection to the array
	 * 
	 * @param string $name the key name of the connection
	 * @param string $host
	 * @param int $port
	 * @param string $charset
	 */
	public static function addConnection($name = 'default', $host = '127.0.0.1', $port = 9306, $charset = 'utf8')
	{
		if ($host === 'localhost')
		{
			$host = '127.0.0.1';
		}
		
		static::$connection_info[$name] = array('host' => $host, 'port' => $port, 'charset' => $charset);
	}
	
	
	/**
	 * Returns the connection info (host, port, charset) for the currently selected connection
	 * 
	 * @return array
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
	 * Sets the connection to use
	 * 
	 * @param string $name
	 */
	public static function setConnection($name)
	{
		static::$current_connection = $name;
	}
	
	
	/**
	 * Returns the \MySQLi connection
	 * 
	 * @return bool|\MySQLi false in case the array key is not found
	 */
	public static function getConnection()
	{
		if (isset(static::$connections[static::$current_connection]))
		{
			return static::$connections[static::$current_connection];
		}
		
		return false;
	}
	
	
	/**
	 * Enstablishes connection to SphinxQL with MySQLi
	 * 
	 * @param string $host
	 * @param int $port
	 * @param type $persistent
	 * @return boolean|\Foolz\Sphinxql\Sphinql
	 */
	public static function connect($suppress_error = false)
	{
		$data = static::getConnectionInfo();
		
		if ( ! $suppress_error && ! static::$silence_connection_warning)
		{
			static::$connections[static::$current_connection] = 
				new \MySQLi($data['host'], '', '', '', $data['port']);
		}
		else
		{
			static::$connections[static::$current_connection] = 
				@ new \MySQLi($data['host'], '', '', '', $data['port']);
		}
				
		if (static::getConnection()->connect_error) 
		{
			throw new SphinxqlConnectionException();
		}
		
		static::getConnection()->set_charset($data['charset']);
		
		return true;
	}
	
	
	/**
	 * Sends the query to Sphinx
	 * 
	 * @param string $query
	 * @return array
	 * @throws SphinxqlDatabaseException
	 */
	public static function query($query)
	{
		static::getConnection() or static::connect();
		
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
			
			return $rows;
		}
		
		// sphinxql doesn't return insert_id because we always have to point it out ourselves!
		return array(static::getConnection()->affected_rows);
	}
	
	/**
	 * 
	 * Taken from FuelPHP and edited
	 * 
	 * @param \Foolz\Sphinxql\SphinxqlExpression $value
	 * @return string
	 */
	public function escape($value)
	{
		if ( ! $conn = static::getConnection() && $conn->connect_errno)
		{
			static::connect();
		}
		
		if (($value = $this->getConnection()->real_escape_string((string) $value)) === false)
		{
			throw new \SphinxqlDatabaseException($this->getConnection()->error, $this->getConnection()->errno);
		}

		return "'".$value."'";
	}
	
	
	/**
	 * Wraps the input in identifiers where necessary
	 * 
	 * @param \Foolz\Sphinxql\SphinxqlExpression|string $value
	 * @return \Foolz\Sphinxql\SphinxqlExpression|string
	 */
	public function quoteIdentifier($value)
	{
		if ($value instanceof \Foolz\Sphinxql\SphinxqlExpression)
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
	 * @param array $array
	 * @return type
	 */
	public function quoteIdentifierArr($array = array())
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
	 * @param \Foolz\Sphinxql\SphinxqlExpression|string $value
	 * @return string
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
		elseif ($value instanceof SphinxqlExpression)
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
	 * Runs $this->quote on every element of an array
	 * 
	 * @param array $array
	 * @return type
	 */
	public function quoteArr($array = array())
	{
		$result = array();
		
		foreach ($array as $key => $item)
		{
			$result[$key] = $this->quote($item);
		}
		
		return $result;
	}

}