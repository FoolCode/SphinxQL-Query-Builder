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
        $this->pool->addServer('server1', '127.0.0.1', 9306);
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 9306), $this->pool->getServer('server1'));

        $this->pool->addServer('server2', 'localhost', 9307);
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 9307), $this->pool->getServer('server2'));
    }


    public function testRemoveServer()
    {
        $this->pool->addServer('server1', '127.0.0.1', 9306);
        $this->assertSame(1, count($this->pool));

        $this->pool->removeServer('server1');
        $this->assertSame(0, count($this->pool->getServers()));
    }
}