<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\Connection as SphinxqlConnection;
use Foolz\Sphinxql\Expression as SphinxqlExpression;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
	private $connection = null;

	public function setUp()
    {
		$this->connection = new SphinxqlConnection();
        $this->connection->silenceConnectionWarning(false);
    }


	public function test()
	{
		new SphinxqlConnection();
	}


	public function testGetConnectionInfo()
	{
		$this->assertSame(array('host' => '127.0.0.1', 'port' => 9306), $this->connection->getConnectionInfo());

		// create a new connection and get info
		$this->connection->setConnection('127.0.0.1', 93067);
		$this->assertSame(array('host' => '127.0.0.1', 'port' => 93067), $this->connection->getConnectionInfo());
	}


	/**
	 * @expectedException Foolz\Sphinxql\ConnectionException
	 */
	public function testGetConnectionThrowsException()
	{
		$this->connection->getConnection();
	}


	public function testConnect()
	{
		$this->connection->connect();
	}


	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 */
	public function testConnectThrowsPHPException()
	{
		$this->connection->setConnection('127.0.0.1', 93067);
		$this->connection->connect();
	}


	public function testPing()
	{
		$this->connection->connect();
		$this->assertTrue($this->connection->ping());
	}

	/**
	 * @expectedException Foolz\Sphinxql\ConnectionException
	 */
	public function testClose()
	{
		$this->connection->connect();
		$this->connection->close();
		$this->connection->getConnection();
	}


	public function testGetConnection()
	{
		$this->connection->connect();
		$this->assertInstanceOf('MySQLi', $this->connection->getConnection());
	}


	/**
	 * @expectedException Foolz\Sphinxql\ConnectionException
	 */
	public function testConnectThrowsException()
	{
		$this->connection->setConnection('127.0.0.1', 93067);
		$this->connection->silenceConnectionWarning(true);
		$this->connection->connect();
	}


	public function testQuery()
	{
		$this->connection->connect();
		$this->assertSame(array(
			array('Variable_name' => 'total', 'Value' => '0'),
			array('Variable_name' => 'total_found', 'Value' => '0'),
			array('Variable_name' => 'time', 'Value' => '0.000'),
		), $this->connection->query('SHOW META'));
	}


	/**
	 * @expectedException Foolz\Sphinxql\DatabaseException
	 */
	public function testQueryThrowsException()
	{
		$this->connection->query('SHOW METAL');
	}


	public function testEscape()
	{
		$this->connection->connect();
		$result = $this->connection->escape('\' "" \'\' ');
		$this->assertEquals('\'\\\' \\"\\" \\\'\\\' \'', $result);
	}


	/**
	 * @expectedException Foolz\Sphinxql\ConnectionException
	 */
	public function testEscapeThrowsException()
	{
		// or we get the wrong error popping up
		$this->connection->setConnection('127.0.0.1', 93067);
		$this->connection->connect();
		$this->connection->escape('\' "" \'\' ');
	}


	public function testQuoteIdentifier()
	{
		// test *
		$this->assertEquals('*', $this->connection->quoteIdentifier('*'));

		// test a normal string
		$this->assertEquals('`foo`.`bar`', $this->connection->quoteIdentifier('foo.bar'));

		// test a SphinxqlExpression
		$this->assertEquals('foo.bar', $this->connection->quoteIdentifier(new SphinxqlExpression('foo.bar')));
	}


	public function testQuoteIdentifierArr()
	{
		$this->assertSame(
			array('*', '`foo`.`bar`', 'foo.bar'),
			$this->connection->quoteIdentifierArr(array('*', 'foo.bar', new SphinxqlExpression('foo.bar')))
		);
	}


	public function testQuote()
	{
		$this->connection->connect();
		$this->assertEquals('null', $this->connection->quote(null));
		$this->assertEquals("'1'", $this->connection->quote(true));
		$this->assertEquals("'0'", $this->connection->quote(false));
		$this->assertEquals("fo'o'bar", $this->connection->quote(new SphinxqlExpression("fo'o'bar")));
		$this->assertEquals("123", $this->connection->quote(123));
		$this->assertEquals("12.3", $this->connection->quote(12.3));
		$this->assertEquals("'12.3'", $this->connection->quote('12.3'));
		$this->assertEquals("'12'", $this->connection->quote('12'));
	}


	public function testQuoteArr()
	{
		$this->connection->connect();
		$this->assertEquals(
			array('null', "'1'", "'0'", "fo'o'bar", "123", "12.3", "'12.3'", "'12'"),
			$this->connection->quoteArr(array(null, true, false, new SphinxqlExpression("fo'o'bar"), 123, 12.3, '12.3', '12'))
		);
	}


}