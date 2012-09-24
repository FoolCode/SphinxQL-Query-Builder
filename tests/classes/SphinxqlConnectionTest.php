<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\SphinxqlConnection;
use Foolz\Sphinxql\SphinxqlExpression;

class SphinxqlConnectionTest extends PHPUnit_Framework_TestCase
{
	
	public function testGetConnectionInfo()
	{
		// get info on a default connection
		$conn_info = Sphinxql::getConnectionInfo();
		$this->assertSame($conn_info, array('host' => '127.0.0.1', 'port' => 9306, 'charset' => 'utf8'));
		
		// create a new connection and get info
		Sphinxql::addConnection('nondefault', 'localhost', 9306, 'utf8');
		$conn_info = Sphinxql::getConnectionInfo('nondefault');
		// localhost gets converted to ip
		$this->assertSame($conn_info, array('host' => '127.0.0.1', 'port' => 9306, 'charset' => 'utf8'));
		
		// brokendefault should throw an error later, there's no such a port
		Sphinxql::addConnection('brokendefault', 'localhost', 93067, 'utf8');
		$conn_info = Sphinxql::getConnectionInfo('brokendefault');
		$this->assertSame($conn_info, array('host' => '127.0.0.1', 'port' => 93067, 'charset' => 'utf8'));
	}
	
	/*
	public function testGetConnection()
	{
		$conn_data = Sphinxql::getConnection('nondefault');
		//$this->assert()
	}
	*/
	
	public function testConnect()
	{
		Sphinxql::setConnection('default');
		$default = Sphinxql::connect();
		
		Sphinxql::setConnection('nondefault');
		$nondefault = Sphinxql::connect();
	}
	
	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 * @expectedException Foolz\Sphinxql\SphinxqlConnectionException
	 */
	public function testConnectThrowsException()
	{
		Sphinxql::setConnection('brokendefault');
		$brokendefault = Sphinxql::connect();
	}
	
	
}