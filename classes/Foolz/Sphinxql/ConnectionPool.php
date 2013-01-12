<?php

namespace Foolz\SphinxQL;

class ConnectionPoolException extends \Exception {};

/**
 * Connection Pool used to handle multiple servers.
 */
class ConnectionPool
{

	/**
	 * Connection Pool array
	 *
	 * @var  array
	 */
	protected $connections = array();


	public function addServer($name, $params = array())
	{
		if ( ! isset($params['host']) && ! isset($params['port']))
		{
			throw new ConnectionPoolException('The server connection parameters are missing.');
		}

		if ($params['host'] === 'localhost')
		{
			$params['host'] = '127.0.0.1';
		}

		$this->connections[$name] = array('host' => $params['host'], 'port' => $params['port']);
	}


	public function getServer($name)
	{
		if ( ! isset($this->connections[$name]))
		{
			throw new ConnectionPoolException('The server is not available in the pool.');
		}

		return $this->connections[$name];
	}


	public function getServers()
	{
		return $this->connections;
	}


	public function removeServer($name)
	{
		if (isset($this->connections[$name]))
		{
			unset($this->connections[$name]);
		}

		return $this;
	}

}