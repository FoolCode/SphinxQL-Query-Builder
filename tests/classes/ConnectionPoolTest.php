<?php

use Foolz\SphinxQL\ConnectionPool as ConnectionPool;

class ConnectionPoolTest extends PHPUnit_Framework_TestCase
{
	private $pool = null;

	public function setUp()
	{
		$this->pool = new ConnectionPool();
	}

	public function test()
	{
		new ConnectionPool();
	}

	public function testAddServer()
	{
		$this->pool->addServer('local', array('host' => '127.0.0.1', 'port' => 9306));
		$this->assertSame(array('host' => '127.0.0.1', 'port' => 9306), $this->pool->getServer('local'));

		$this->pool->addServer('remote', array('host' => 'remote.dns', 'port' => 9306));
		$this->assertSame(array('host' => 'remote.dns', 'port' => 9306), $this->pool->getServer('remote'));
	}


	public function testRemoveServer()
	{
		$this->pool->addServer('local', array('host' => '127.0.0.1', 'port' => 9306));
		$this->assertSame(1, count($this->pool));

		$this->pool->removeServer('local');
		$this->assertSame(0, count($this->pool->getServers()));
	}
}