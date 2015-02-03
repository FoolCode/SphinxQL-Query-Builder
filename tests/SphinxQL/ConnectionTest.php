<?php

use Foolz\SphinxQL\Expression;
use Foolz\SphinxQL\Tests\TestUtil;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    private $connection = null;

    public function setUp()
    {
        $this->connection = TestUtil::getConnectionDriver();
        $this->connection->setParams(array('host' => '127.0.0.1', 'port' => 9307));
        $this->connection->silenceConnectionWarning(false);
    }

    public function tearDown()
    {
        $this->connection = null;
    }

    public function test()
    {
        TestUtil::getConnectionDriver();
    }

    /**
     * @covers \Foolz\SphinxQL\Connection::setParam
     * @covers \Foolz\SphinxQL\Connection::setParams
     */
    public function testGetParams()
    {
        $this->assertSame(
            array('host' => '127.0.0.1', 'port' => 9307, 'socket' => null),
            $this->connection->getParams()
        );

        // create a new connection and get info
        $this->connection->setParams(array('host' => '127.0.0.2'));
        $this->connection->setParam('port', 9308);
        $this->assertSame(
            array('host' => '127.0.0.2', 'port' => 9308, 'socket' => null),
            $this->connection->getParams()
        );

        // create a unix socket connection with host param
        $this->connection->setParam('host', 'unix:/var/run/sphinx.sock');
        $this->assertSame(
            array('host' => null, 'port' => 9308, 'socket' => '/var/run/sphinx.sock'),
            $this->connection->getParams()
        );

        // create unix socket connection with socket param
        $this->connection->setParam('host', '127.0.0.1');
        $this->connection->setParam('socket', '/var/run/sphinx.sock');
        $this->assertSame(
            array('host' => null, 'port' => 9308, 'socket' => '/var/run/sphinx.sock'),
            $this->connection->getParams()
        );
    }

    /**
     * @covers \Foolz\SphinxQL\Connection::setParams
     * @covers \Foolz\SphinxQL\Connection::getParams
     */
    public function testGetConnectionParams()
    {
        // verify that (deprecated) getConnectionParams continues to work
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 9307, 'socket' => null), $this->connection->getParams());

        // create a new connection and get info
        $this->connection->setParams(array('host' => '127.0.0.1', 'port' => 9308));
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 9308, 'socket' => null), $this->connection->getParams());
    }

    public function testGetConnection()
    {
        $this->connection->connect();
        $this->assertNotNull($this->connection->getConnection());
    }

    /**
     * @expectedException Foolz\SphinxQL\Exception\ConnectionException
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
        $this->connection->setParam('port', 9308);
        $this->connection->connect();
    }

    /**
     * @expectedException Foolz\SphinxQL\Exception\ConnectionException
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
     * @expectedException Foolz\SphinxQL\Exception\ConnectionException
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
     * @expectedException Foolz\SphinxQL\Exception\DatabaseException
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

}
