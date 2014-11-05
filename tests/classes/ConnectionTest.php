<?php

use Foolz\SphinxQL\Connection;
use Foolz\SphinxQL\Expression;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
    private $connection = null;

    public function setUp()
    {
        $this->connection = new Connection();
        $this->connection->setParams(array('host' => '127.0.0.1', 'port' => 9307));
        $this->connection->silenceConnectionWarning(false);
    }

	public function tearDown()
	{
		$this->connection->close();
		$this->connection = null;
	}

    public function test()
    {
        new Connection();
    }

    /**
     * @covers \Foolz\SphinxQL\Connection::setParam
     * @covers \Foolz\SphinxQL\Connection::setParams
     */
    public function testGetParams()
    {
        $this->assertSame(
            array('host' => '127.0.0.1', 'port' => 9307),
            $this->connection->getParams()
        );

        // create a new connection and get info
        $this->connection->setParams(array('host' => '127.0.0.2'));
        $this->connection->setParam('port', 9308);
        $this->assertSame(
            array('host' => '127.0.0.2', 'port' => 9308),
            $this->connection->getParams()
        );
    }

    /**
     * @covers \Foolz\SphinxQL\Connection::setConnectionParams
     */
    public function testGetConnectionParams()
    {
        // verify that (deprecated) getConnectionParams continues to work
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 9307), $this->connection->getConnectionParams());

        // create a new connection and get info
        $this->connection->setConnectionParams('127.0.0.1', 9308);
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 9308), $this->connection->getConnectionParams());
    }

    public function testGetConnection()
    {
        $this->connection->connect();
        $this->assertInstanceOf('MySQLi', $this->connection->getConnection());
    }

    /**
     * @expectedException Foolz\SphinxQL\ConnectionException
     */
    public function testGetConnectionThrowsException()
    {
        $this->connection->getConnection();
    }

	/**
	 * @covers \Foolz\SphinxQL\Connection::connect
	 * @covers \Foolz\SphinxQL\Connection::setEncoding
	 * @covers \Foolz\SphinxQL\Connection::getEncoding
	 */
	public function testConnect()
	{
		$this->assertEquals(true, $this->connection->connect());

		// and that encoding was set
		// somehow it fails on my instance, but doesn't throw an error
		$this->assertEquals('utf8', $this->connection->getEncoding());
	}

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testConnectThrowsPHPException()
    {
        $this->connection->setParam('port', 9308);
        $this->connection->connect();
    }

    /**
     * @expectedException Foolz\SphinxQL\ConnectionException
     */
    public function testConnectThrowsException()
    {
        $this->connection->setParam('port', 9308);
        $this->connection->silenceConnectionWarning(true);
        $this->connection->connect();
    }


    public function testPing()
    {
        $this->connection->connect();
        $this->assertTrue($this->connection->ping());
    }

    /**
     * @expectedException Foolz\SphinxQL\ConnectionException
     */
    public function testClose()
    {
        $this->connection->connect();
        $this->connection->close();
        $this->connection->getConnection();
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

    public function testMultiQuery()
    {
        $this->connection->connect();
        $this->assertSame(array(array(
            array('Variable_name' => 'total', 'Value' => '0'),
            array('Variable_name' => 'total_found', 'Value' => '0'),
            array('Variable_name' => 'time', 'Value' => '0.000'),
        )), $this->connection->multiQuery(array('SHOW META')));
    }

    /**
     * @expectedException Foolz\SphinxQL\DatabaseException
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
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testEscapeThrowsException()
    {
        // or we get the wrong error popping up
        $this->connection->setParam('port', 9308);
        $this->connection->connect();
        $this->connection->escape('\' "" \'\' ');
    }

    public function testQuoteIdentifier()
    {
        // test *
        $this->assertEquals('*', $this->connection->quoteIdentifier('*'));

        // test a normal string
        $this->assertEquals('`foo`.`bar`', $this->connection->quoteIdentifier('foo.bar'));

        // test a SphinxQLExpression
        $this->assertEquals('foo.bar', $this->connection->quoteIdentifier(new Expression('foo.bar')));
    }

    public function testQuoteIdentifierArr()
    {
        $this->assertSame(
            array('*', '`foo`.`bar`', 'foo.bar'),
            $this->connection->quoteIdentifierArr(array('*', 'foo.bar', new Expression('foo.bar')))
        );
    }

    public function testQuote()
    {
        $this->connection->connect();
        $this->assertEquals('null', $this->connection->quote(null));
        $this->assertEquals("'1'", $this->connection->quote(true));
        $this->assertEquals("'0'", $this->connection->quote(false));
        $this->assertEquals("fo'o'bar", $this->connection->quote(new Expression("fo'o'bar")));
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
            $this->connection->quoteArr(array(null, true, false, new Expression("fo'o'bar"), 123, 12.3, '12.3', '12'))
        );
    }

	/**
	 * @covers \Foolz\SphinxQL\Connection::setEncoding
	 */
	public function testSetEncoding()
	{
		mb_internal_encoding("KOI8-R");
		$this->connection->connect();
		$this->assertEquals("UTF-8", mb_internal_encoding());

		$this->connection->close();
		$this->assertEquals("KOI8-R", mb_internal_encoding());
	}
}
