<?php
namespace Foolz\SphinxQL\Tests\Drivers;

use Foolz\SphinxQL\Drivers\Mysqli\Connection;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Expression;
use Foolz\SphinxQL\Tests\TestUtil;

use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase{

    /**
     * @var Connection $connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = TestUtil::getConnectionDriver();
        $this->connection->setParam('port',9307);
    }

    protected function tearDown(): void
    {
        $this->connection = null;
    }

    public function test(): void
    {
        self::assertNotNull(TestUtil::getConnectionDriver());
    }

    public function testGetParams(): void
    {
        $this->assertSame([
			'host'		=> '127.0.0.1',
			'port'		=> 9307,
			'socket'	=> null,
		],$this->connection->getParams());

        // create a new connection and get info
        $this->connection->setParams([
			'host'		=> '127.0.0.2',
		]);
        $this->connection->setParam('port',9308);
        $this->assertSame([
			'host'		=> '127.0.0.2',
			'port'		=> 9308,
			'socket'	=> null,
		],$this->connection->getParams());

        $this->connection->setParam('host','localhost');
        $this->assertSame([
			'host'		=> '127.0.0.1',
			'port'		=> 9308,
			'socket'	=> null,
		],$this->connection->getParams());

        // create a unix socket connection with host param
        $this->connection->setParam('host','unix:/var/run/sphinx.sock');
        $this->assertSame([
			'host'		=> null,
			'port'		=> 9308,
			'socket'	=> '/var/run/sphinx.sock',
		],$this->connection->getParams());

        // create unix socket connection with socket param
        $this->connection->setParam('host', '127.0.0.1');
        $this->connection->setParam('socket', '/var/run/sphinx.sock');
        $this->assertSame([
			'host'		=> null,
			'port'		=> 9308,
			'socket'	=> '/var/run/sphinx.sock',
		],$this->connection->getParams());
    }

    public function testGetConnectionParams(): void
    {
        // verify that (deprecated) getConnectionParams continues to work
        $this->assertSame([
			'host'		=> '127.0.0.1',
			'port'		=> 9307,
			'socket'	=> null,
		],$this->connection->getParams());

        // create a new connection and get info
        $this->connection->setParams([
			'host'		=> '127.0.0.1',
			'port'		=> 9308,
		]);
        $this->assertSame([
			'host'		=> '127.0.0.1',
			'port'		=> 9308,
			'socket'	=> null,
		],$this->connection->getParams());
    }

    /**
     * @throws ConnectionException
     */
    public function testGetConnection(): void
    {
        $this->connection->connect();
        $this->assertNotNull($this->connection->getConnection());
    }

    /**
     * @throws ConnectionException
     */
    public function testGetConnectionThrowsException(): void
    {
        $this->expectException(ConnectionException::class);

        $this->connection->getConnection();
    }

    /**
     * @throws ConnectionException
     */
    public function testConnect(): void
    {
        $this->connection->connect();

        $this->connection->setParam('options',[
			MYSQLI_OPT_CONNECT_TIMEOUT => 1,
		]);
        self::assertIsBool($this->connection->connect());
    }

    /**
     * @throws ConnectionException
     */
    public function testConnectThrowsException(): void
    {
        $this->expectException(ConnectionException::class);

        $this->connection->setParam('port', 9308);
        $this->connection->connect();
    }

    /**
     * @throws ConnectionException
     */
    public function testPing(): void
    {
        $this->connection->connect();
        $this->assertTrue($this->connection->ping());
    }

    /**
     * @throws ConnectionException
     */
    public function testClose(): void
    {
        $this->expectException(ConnectionException::class);

        $encoding = mb_internal_encoding();
        $this->connection->connect();

        if (method_exists($this->connection, 'getInternalEncoding')) {
            $this->assertEquals($encoding, $this->connection->getInternalEncoding());
            $this->assertEquals('UTF-8', mb_internal_encoding());
        }

        $this->connection->close();
        $this->assertEquals($encoding, mb_internal_encoding());
        $this->connection->getConnection();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testQuery(): void
    {
        $this->connection->connect();

        $this->assertSame([
			[
				'Variable_name'		=> 'total',
				'Value'				=> '0',
			],
			[
				'Variable_name'		=> 'total_found',
				'Value'				=> '0',
			],
			[
				'Variable_name'		=> 'time',
				'Value'				=> '0.000',
			],
		],$this->connection->query('SHOW META')->store()->fetchAllAssoc());

    }

    //TODO
//    /**
//     * @throws ConnectionException
//     * @throws SphinxQLException
//     * @throws DatabaseException
//     */
//    public function testMultiQuery(): void
//    {
//        $this->connection->connect();
//        $query = $this->connection->multiQuery(['SHOW META']);
//
//        $result = $query->getNext();
//
//        $resultArr = [];
//        if ($result) {
//            $resultArr = $result->fetchAllAssoc();
//        }
//
//        $this->assertSame([
//			[
//				'Variable_name'		=> 'total',
//				'Value'				=> '0',
//			],
//			[
//				'Variable_name'		=> 'total_found',
//				'Value'				=> '0',
//			],
//			[
//				'Variable_name'		=> 'time',
//				'Value'				=> '0.000',
//			],
//		], $resultArr);
//    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testEmptyMultiQuery(): void
    {
        $this->expectException(SphinxQLException::class);
        $this->expectExceptionMessage('The Queue is empty.');
        
        $this->connection->connect();
        $this->connection->multiQuery([]);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testMultiQueryThrowsException(): void
    {
        $this->expectException(DatabaseException::class);

        $this->connection->multiQuery(['SHOW METAL']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testQueryThrowsException(): void
    {
        $this->expectException(DatabaseException::class);

        $this->connection->query('SHOW METAL');
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testEscape(): void
    {
        $result = $this->connection->escape('\' "" \'\' ');
        $this->assertEquals('\'\\\' \\"\\" \\\'\\\' \'', $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testEscapeThrowsException(): void
    {
        $this->expectException(ConnectionException::class);

        // or we get the wrong error popping up
        $this->connection->setParam('port', 9308);
        $this->connection->connect();
        $this->connection->escape('\' "" \'\' ');
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testQuote(): void
    {
        $this->connection->connect();
        $this->assertEquals('null', $this->connection->quote(null));
        $this->assertEquals(1, $this->connection->quote(true));
        $this->assertEquals(0, $this->connection->quote(false));
        $this->assertEquals("fo'o'bar", $this->connection->quote(new Expression("fo'o'bar")));
        $this->assertEquals(123, $this->connection->quote(123));
        $this->assertEquals('12.300000', $this->connection->quote(12.3));
        $this->assertEquals("'12.3'", $this->connection->quote('12.3'));
        $this->assertEquals("'12'", $this->connection->quote('12'));
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testQuoteArr(): void
    {
        $this->connection->connect();
        $this->assertEquals(['null', 1, 0, "fo'o'bar", 123, '12.300000', "'12.3'", "'12'"],
            $this->connection->quoteArr([null, true, false, new Expression("fo'o'bar"), 123, 12.3, '12.3', '12']));
    }

}