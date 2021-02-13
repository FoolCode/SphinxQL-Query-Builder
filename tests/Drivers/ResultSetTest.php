<?php
namespace Foolz\SphinxQL\Tests\Drivers;

use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\Sphinxql\Drivers\ResultSetInterface;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\ResultSetException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Tests\TestUtil;

use PHPUnit\Framework\TestCase;

class ResultSetTest extends TestCase{

    /**
     * @var ConnectionBase $conn
     */
    public static $connection;

    public static $DATA = [
        0 => [
			'id'		=> '10',
			'gid'		=> '9003',
			'title'		=> 'modifying the same line again',
			'content'	=> 'because i am that lazy',
		],
        1 => [
			'id'		=> '11',
			'gid'		=> '201',
			'title'		=> 'replacing value by value',
			'content'	=> 'i have no idea who would use this directly',
		],
        2 => [
			'id'		=> '12',
			'gid'		=> '200',
			'title'		=> 'simple logic',
			'content'	=> 'inside the box there was the content',
		],
        3 => [
			'id'		=> '13',
			'gid'		=> '304',
			'title'		=> 'i am getting bored',
			'content'	=> 'with all this CONTENT',
		],
        4 => [
			'id'		=> '14',
			'gid'		=> '304',
			'title'		=> 'i want a vacation',
			'content'	=> 'the code is going to break sometime',
		],
        5 => [
			'id'		=> '15',
			'gid'		=> '304',
			'title'		=> 'there\'s no hope in this class',
			'content'	=> 'just give up',
		],
        6 => [
			'id'		=> '16',
			'gid'		=> '500',
			'title'		=> 'we need to test',
			'content'	=> 'selecting the best result in groups',
		],
        7 => [
			'id'		=> '17',
			'gid'		=> '500',
			'title'		=> 'what is there to do',
			'content'	=> 'we need to create dummy data for tests',
		],
    ];

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public static function setUpBeforeClass(): void
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        self::$connection = $conn;

        (new SphinxQL(self::$connection))->getConnection()->query('TRUNCATE RTINDEX rt');
    }

    /**
     * @return SphinxQL
     */
    protected function createSphinxQL(): SphinxQL
    {
        return new SphinxQL(self::$connection);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function refill(): void
    {
        $this->createSphinxQL()->getConnection()->query('TRUNCATE RTINDEX rt');

        $sq = $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->columns('id', 'gid', 'title', 'content');

        foreach (static::$DATA as $row) {
            $sq->values($row['id'], $row['gid'], $row['title'], $row['content']);
        }

        $sq->execute();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testIsResultSet(): void
    {
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertInstanceOf(ResultSetInterface::class, $res);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testStore(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $res->store()->store();
        $this->assertCount(8, $res->fetchAllNum());

        $res = self::$connection->query('UPDATE rt SET gid = 202 WHERE gid < 202');
        $this->assertEquals(2, $res->store()->getAffectedRows());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testHasRow(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertTrue($res->hasRow(2));
        $this->assertTrue(isset($res[2]));
        $this->assertFalse($res->hasRow(1000));
        $this->assertFalse(isset($res[1000]));
        $res->freeResult();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws ResultSetException
     * @throws SphinxQLException
     */
    public function testToRow(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $res->toRow(2);
        $row = $res->fetchAssoc();
        $this->assertEquals(12, $row['id']);
        $res->freeResult();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws ResultSetException
     * @throws SphinxQLException
     */
    public function testToRowThrows(): void
    {
        $this->expectException(ResultSetException::class);
        $this->expectExceptionMessage('The row does not exist.');

        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $res->toRow(8);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testHasNextRow(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertTrue($res->hasNextRow());
        $res->freeResult();
        $res = self::$connection->query('SELECT * FROM rt WHERE id = 9000');
        $this->assertFalse($res->hasNextRow());
        $res->freeResult();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws ResultSetException
     * @throws SphinxQLException
     */
    public function testToNextRow(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $res->toNextRow()->toNextRow()->toNextRow();
        $row = $res->fetchAssoc();
        $this->assertEquals(13, $row['id']);
        $res->freeResult();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws ResultSetException
     * @throws SphinxQLException
     */
    public function testToNextRowThrows(): void
    {
        $this->expectException(ResultSetException::class);
        $this->expectExceptionMessage('The row does not exist.');

        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt WHERE id = 10');
        $res->toNextRow()->toNextRow();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testCount(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertEquals(8, $res->count());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testFetchAllAssoc(): void
    {
        $expect = array(
            0 => array(
                'id' => '10',
                'gid' => '9003'
            ),
            1 => array(
                'id' => '11',
                'gid' => '201'
            )
        );


        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $array = $res->fetchAllAssoc();
        $this->assertSame($expect[0], $array[0]);
        $this->assertSame($expect[1], $array[1]);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testFetchAssoc(): void
    {
        $expect = array(
            0 => array(
                'id' => '10',
                'gid' => '9003'
            ),
            1 => array(
                'id' => '11',
                'gid' => '201'
            )
        );


        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertSame($expect[0], $res->fetchAssoc());
        $this->assertSame($expect[1], $res->fetchAssoc());
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $this->assertNull($res->fetchAssoc());

        $res = self::$connection->query('SELECT * FROM rt')->store();
        $this->assertSame($expect[0], $res->fetchAssoc());
        $this->assertSame($expect[1], $res->fetchAssoc());
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $res->fetchAssoc();
        $this->assertNull($res->fetchAssoc());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testFetchAllNum(): void
    {
        $expect = array(
            0 => array(
                0 => '10',
                1 => '9003'
            ),
            1 => array(
                0 => '11',
                1 => '201'
            )
        );

        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt LIMIT 2');
        $array = $res->fetchAllNum();
        $this->assertSame($expect, $array);

        $res = self::$connection->query('SELECT * FROM rt LIMIT 2');
        $array = $res->store()->fetchAllNum();
        $this->assertSame($expect, $array);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testFetchNum(): void
    {
        $expect = array(
            0 => array(
                0 => '10',
                1 => '9003'
            ),
            1 => array(
                0 => '11',
                1 => '201'
            )
        );

        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertSame($expect[0], $res->fetchNum());
        $this->assertSame($expect[1], $res->fetchNum());
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $this->assertNull($res->fetchNum());

        $res = self::$connection->query('SELECT * FROM rt')->store();
        $this->assertSame($expect[0], $res->fetchNum());
        $this->assertSame($expect[1], $res->fetchNum());
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $res->fetchNum();
        $this->assertNull($res->fetchNum());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testGetAffectedRows(): void
    {
        $this->refill();
        $res = self::$connection->query('UPDATE rt SET gid=0 WHERE id > 0');
        $this->assertSame(8, $res->getAffectedRows());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testArrayAccess(): void
    {
        $expect = array(
            0 => array(
                'id' => '10',
                'gid' => '9003'
            ),
            1 => array(
                'id' => '11',
                'gid' => '201'
            )
        );


        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertSame($expect[0], $res[0]);
        $this->assertSame($expect[1], $res[1]);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testCountable(): void
    {
        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $this->assertCount($res->count(), $res);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testIterator(): void
    {
        $expect = array(
            0 => array(
                'id' => '10',
                'gid' => '9003'
            ),
            1 => array(
                'id' => '11',
                'gid' => '201'
            )
        );

        $this->refill();
        $res = self::$connection->query('SELECT * FROM rt');
        $array = array();
        foreach ($res as $key => $value) {
            $array[$key] = $value;
        }

        $this->assertSame($expect[0], $array[0]);
        $this->assertSame($expect[1], $array[1]);

        $res = self::$connection->query('SELECT * FROM rt WHERE id = 404');
        $array = array();
        foreach ($res as $key => $value) {
            $array[$key] = $value;
        }
        $this->assertEmpty($array);
    }

}