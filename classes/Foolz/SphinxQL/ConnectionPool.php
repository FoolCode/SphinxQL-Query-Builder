<?php

namespace Foolz\SphinxQL;

class ConnectionPoolException extends \Exception {};

/**
 * Connection Pool used to handle and manage multiple servers.
 */
class ConnectionPool
{
    /**
     * Connection Pool array
     *
     * @var  array
     */
    protected $connections = array();

    /**
     * Add a new server to the connection pool
     *
     * @param  string  $server_name  The name of the server
     * @param  string  $host         The hostname or IP
     * @param  int     $port         The port to the host
     */
    public function addServer($server_name, $host = '127.0.0.1', $port = 9306)
    {
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $this->connections[$server_name] = array('host' => $host, 'port' => $port);
    }

    /**
     * Get the connection params for the specified server in the pool
     *
     * @param  string$server_name
     *
     * @return  array
     * @throws  ConnectionPoolException
     */
    public function getServer($server_name)
    {
        if ( ! isset($this->connections[$server_name])) {
            throw new ConnectionPoolException('The server is not available in the pool.');
        }

        return $this->connections[$server_name];
    }

    /**
     * @return  array  The list of servers in the connection pool
     */
    public function getServers()
    {
        return $this->connections;
    }

    /**
     * Remove a server from the connection pool
     *
     * @param $server_name
     *
     * @return \Foolz\SphinxQL\ConnectionPool
     */
    public function removeServer($server_name)
    {
        if (isset($this->connections[$server_name])) {
            unset($this->connections[$server_name]);
        }

        return $this;
    }
}