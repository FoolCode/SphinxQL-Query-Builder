<?php

namespace Foolz\Sphinxql;

class SphinxqlConnection
{
	/**
	 * The current connection
	 * 
	 * @var object
	 */
	protected $conn;
	
	public static function forge($host = 'localhost', $port = 9306, $charset = 'utf8')
	{
		$class = new Sphinxql;
		$class->setConnection($host, $port, $charset);
		return $class;
	}
	
	/**
	 * Enstablishes connection to SphinxQL with MySQLi
	 * 
	 * @param string $host
	 * @param int $port
	 * @param type $persistent
	 * @return boolean|\Foolz\Sphinxql\Sphinql
	 */
	public function setConnection($host = 'localhost', $port = 9306, $charset = 'utf8')
	{
		$this->conn = new \MySQLi($host, null, null, null, $port);
		
		if ($this->conn->connect_error) 
		{
			return false;
		}
		
		$this->conn->set_charset($charset);
	
		return $this;
	}
	
	public function query($query)
	{
		$resource = $this->conn->query($query);
		
		if ($this->conn->error)
		{
			throw new SphinxqlDatabaseException('['.$this->conn->errno.'] '.$this->conn->error);
		}
		
		if($resource instanceof \mysqli_result)
		{
			return $resource->fetch_all(MYSQLI_ASSOC);
		}
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
		if (($value = $this->conn->real_escape_string((string) $value)) === false)
		{
			throw new \SphinxqlDatabaseException($this->conn->error, $this->conn->errno);
		}
		
		return "'".$this->conn->real_escape_string($value)."'";
	}
	
	
	public function quoteIdentifier($value)
	{
		if ($value instanceof SphinxqlExpression)
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
	
	
	public function quoteIdentifiersArr($array = array())
	{
		$result = array();
		
		foreach ($array as $key => $item)
		{
			$result[$key] = $this->quoteIdentifier($item);
		}
		
		return $result;
	}
	
	/**
	 * 
	 * Taken from FuelPHP and edited
	 * 
	 * @param \Foolz\Sphinxql\SphinxqlExpression $value
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

}