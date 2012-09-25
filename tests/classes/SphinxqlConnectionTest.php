<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\SphinxqlConnection;
use Foolz\Sphinxql\SphinxqlExpression;

class SphinxqlConnectionTest extends PHPUnit_Framework_TestCase
{
	
	
	public function setUp()
    {
		// always disable the silencing
        SphinxqlConnection::silenceConnectionWarning(false);
    }
	
	
	public function test()
	{
		new SphinxqlConnection();
	}
	
	
	public function testGetConnectionInfo()
	{
		// get info on a default connection
		$conn_info = SphinxqlConnection::getConnectionInfo();
		$this->assertSame(array('host' => '127.0.0.1', 'port' => 9306, 'charset' => 'utf8'), $conn_info);
		
		// create a new connection and get info
		SphinxqlConnection::addConnection('nondefault', 'localhost', 9306, 'utf8');
		$conn_info = SphinxqlConnection::getConnectionInfo('nondefault');
		// localhost gets converted to ip
		$this->assertSame(array('host' => '127.0.0.1', 'port' => 9306, 'charset' => 'utf8'), $conn_info);
		
		// brokendefault should throw an error later, there's no such a port
		SphinxqlConnection::addConnection('brokendefault', 'localhost', 93067, 'utf8');
		$conn_info = SphinxqlConnection::getConnectionInfo('brokendefault');
		$this->assertSame(array('host' => '127.0.0.1', 'port' => 93067, 'charset' => 'utf8'), $conn_info);
	}
	
	
	/**
	 * @expectedException Foolz\Sphinxql\SphinxqlConnectionException
	 */
	public function testGetConnectionThrowsException()
	{
		SphinxqlConnection::setConnection('nondefault');
		$conn_data = Sphinxql::getConnection();
	}

	
	public function testConnect()
	{
		SphinxqlConnection::setConnection('default');
		$default = SphinxqlConnection::connect();
		
		SphinxqlConnection::setConnection('nondefault');
		$nondefault = SphinxqlConnection::connect();
	}
	
	
	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 */
	public function testConnectThrowsPHPException()
	{
		SphinxqlConnection::setConnection('brokendefault');
		SphinxqlConnection::connect();
	}

	
	public function testGetConnection()
	{
		Sphinxql::setConnection('nondefault');
		$conn_data = Sphinxql::getConnection();
		$this->assertInstanceOf('MySQLi', $conn_data);
	}
	
	
	/**
	 * @expectedException Foolz\Sphinxql\SphinxqlConnectionException
	 */
	public function testConnectThrowsException()
	{
		SphinxqlConnection::setConnection('brokendefault');
		SphinxqlConnection::silenceConnectionWarning(true);
		SphinxqlConnection::connect();
	}
	
	
	public function testQuery()
	{
		SphinxqlConnection::setConnection('nondefault');
		$this->assertSame(array(
			array('Variable_name' => 'total', 'Value' => '0'), 
			array('Variable_name' => 'total_found', 'Value' => '0'),
			array('Variable_name' => 'time', 'Value' => '0.000'),
		), Sphinxql::query('SHOW META'));		
	}
	
	
	/**
	 * @expectedException Foolz\Sphinxql\SphinxqlDatabaseException
	 */
	public function testQueryThrowsException()
	{
		SphinxqlConnection::query('SHOW METAL');
	}
	
	
	public function testEscape()
	{
		$sq = SphinxqlConnection::forge();
		$result = $sq->escape('\' "" \'\' ');
		$this->assertEquals('\'\\\' \\"\\" \\\'\\\' \'', $result);
	}
	
	
	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 */
	public function testEscapeThrowsException()
	{
		// or we get the wrong error popping up
		SphinxqlConnection::silenceConnectionWarning(true);		
		SphinxqlConnection::setConnection('brokendefault');
		$sq = Sphinxql::forge();
		$sq->escape('\' "" \'\' ');
	}
	
	
	public function testQuoteIdentifier()
	{
		SphinxqlConnection::setConnection('nondefault');
		
		$sq = SphinxqlConnection::forge();
		
		// test *
		$this->assertEquals('*', $sq->quoteIdentifier('*'));
		
		// test a normal string
		$this->assertEquals('`foo`.`bar`', $sq->quoteIdentifier('foo.bar'));
		
		// test a SphinxqlExpression
		$this->assertEquals('foo.bar', $sq->quoteIdentifier(new SphinxqlExpression('foo.bar')));
	}
	
	
	public function testQuoteIdentifierArr()
	{
		$sq = SphinxqlConnection::forge();
		
		$this->assertSame(
			array('*', '`foo`.`bar`', 'foo.bar'),
			$sq->quoteIdentifierArr(array('*', 'foo.bar', new SphinxqlExpression('foo.bar')))
		);
	}
	
	
	public function testQuote()
	{
		$sq = SphinxqlConnection::forge();
		
		$this->assertEquals('null', $sq->quote(null));
		$this->assertEquals("'1'", $sq->quote(true));
		$this->assertEquals("'0'", $sq->quote(false));
		$this->assertEquals("fo'o'bar", $sq->quote(new SphinxqlExpression("fo'o'bar")));
		$this->assertEquals("123", $sq->quote(123));
		$this->assertEquals("12.3", $sq->quote(12.3));
		$this->assertEquals("'12.3'", $sq->quote('12.3'));
		$this->assertEquals("'12'", $sq->quote('12'));
	}
	
	
	public function testQuoteArr()
	{
		$sq = SphinxqlConnection::forge();
		
		$this->assertEquals(
			array('null', "'1'", "'0'", "fo'o'bar", "123", "12.3", "'12.3'", "'12'"),
			$sq->quoteArr(array(null, true, false, new SphinxqlExpression("fo'o'bar"), 123, 12.3, '12.3', '12'))
		);
	}
	
	
}