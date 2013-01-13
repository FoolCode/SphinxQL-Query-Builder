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

	public function addServer($server_name, $host = '127.0.0.1', $port = 9306)
	{
		if ($host === 'localhost')
		{
			$host = '127.0.0.1';
		}

		$this->connections[$server_name] = array('host' => $host, 'port' => $port);
	}

	public function getServer($server_name)
	{
		if ( ! isset($this->connections[$server_name]))
		{
			throw new ConnectionPoolException('The server is not available in the pool.');
		}

		return $this->connections[$server_name];
	}

	public function getServers()
	{
		return $this->connections;
	}

	public function removeServer($server_name)
	{
		if (isset($this->connections[$server_name]))
		{
			unset($this->connections[$server_name]);
		}

		return $this;
	}
}