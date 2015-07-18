<?php

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Drivers\Mysqli\Connection;
use Foolz\SphinxQL\Tests\TestUtil;

class ResultSetTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    public static $conn = null;

    public static $data = array(
        0 => array('id' => '10', 'gid' => '9003',
            'title' => 'modifying the same line again', 'content' => 'because i am that lazy'),
        1 => array('id' => '11', 'gid' => '201',
            'title' => 'replacing value by value', 'content' => 'i have no idea who would use this directly'),
        2 => array('id' => '12', 'gid' => '200',
            'title' => 'simple logic', 'content' => 'inside the box there was the content'),
        3 => array('id' => '13', 'gid' => '304',
            'title' => 'i am getting bored', 'content' => 'with all this CONTENT'),
        4 => array('id' => '14', 'gid' => '304',
            'title' => 'i want a vacation', 'content' => 'the code is going to break sometime'),
        5 => array('id' => '15', 'gid' => '304',
            'title' => 'there\'s no hope in this class', 'content' => 'just give up'),
        6 => array('id' => '16', 'gid' => '500',
            'title' => 'we need to test', 'content' => 'selecting the best result in groups'),
        7 => array('id' => '17', 'gid' => '500',
            'title' => 'what is there to do', 'content' => 'we need to create dummy data for tests'),
    );

    public static function setUpBeforeClass()
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        self::$conn = $conn;

        SphinxQL::create(self::$conn)->getConnection()->query('TRUNCATE RTINDEX rt');
    }

    public function refill()
    {
        SphinxQL::create(self::$conn)->getConnection()->query('TRUNCATE RTINDEX rt');

        $sq = SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->columns('id', 'gid', 'title', 'content');

        foreach (static::$data as $row) {
            $sq->values($row['id'], $row['gid'], $row['title'], $row['content']);
        }

        $sq->execute();
    }

    public function testIsResultSet()
    {
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertInstanceOf('\Foolz\Sphinxql\Drivers\ResultSetInterface', $res);
    }

    public function testStore()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $res->store()->store();
        $this->assertCount(8, $res->fetchAllNum());

        $res = self::$conn->query('UPDATE rt SET gid = 202 WHERE gid < 202');
        $this->assertEquals(2, $res->store()->getStored());
    }

    public function testHasRow()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertTrue($res->hasRow(2));
        $this->assertTrue(isset($res[2]));
        $this->assertFalse($res->hasRow(1000));
        $this->assertFalse(isset($res[1000]));
        $res->freeResult();
    }

    public function testToRow()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $res->toRow(2);
        $row = $res->fetchAssoc();
        $this->assertEquals(12, $row['id']);
        $res->freeResult();
    }

    /**
     * @expectedException        Foolz\SphinxQL\Exception\ResultSetException
     * @expectedExceptionMessage The row does not exist.
     */
    public function testToRowThrows()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $res->toRow(8);
    }

    public function testHasNextRow()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertTrue($res->hasNextRow());
        $res->freeResult();
        $res = self::$conn->query('SELECT * FROM rt WHERE id = 9000');
        $this->assertFalse($res->hasNextRow());
        $res->freeResult();
    }

    public function testToNextRow()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $res->toNextRow()->toNextRow()->toNextRow();
        $row = $res->fetchAssoc();
        $this->assertEquals(12, $row['id']);
        $res->freeResult();

        $res = self::$conn->query('SELECT * FROM rt WHERE id = 10');
        $res->toNextRow();
        $row = $res->fetchAssoc();
        $this->assertEquals(10, $row['id']);
        $res->freeResult();
    }

    /**
     * @expectedException        Foolz\SphinxQL\Exception\ResultSetException
     * @expectedExceptionMessage The next row does not exist.
     */
    public function testToNextRowThrows()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt WHERE id = 10');
        $res->toNextRow()->toNextRow();
    }

    public function testGetCount()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertEquals(8, $res->getCount());
    }

    public function testFetchAllAssoc()
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
        $res = self::$conn->query('SELECT * FROM rt');
        $array = $res->fetchAllAssoc();
        $this->assertSame($expect[0], $array[0]);
        $this->assertSame($expect[1], $array[1]);
    }

    public function testFetchAssoc()
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
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertSame($expect[0], $res->toNextRow()->fetchAssoc());
        $this->assertSame($expect[1], $res->toNextRow()->fetchAssoc());
    }

    public function testFetchAllNum()
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
        $res = self::$conn->query('SELECT * FROM rt LIMIT 2');
        $array = $res->fetchAllNum();
        $this->assertSame($expect, $array);

        $res = self::$conn->query('SELECT * FROM rt LIMIT 2');
        $array = $res->store()->fetchAllNum();
        $this->assertSame($expect, $array);
    }

    public function testFetchNum()
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
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertSame($expect[0], $res->toNextRow()->fetchNum());
        $this->assertSame($expect[1], $res->toNextRow()->fetchNum());

        $res = self::$conn->query('SELECT * FROM rt')->store();
        $this->assertSame($expect[0], $res->toNextRow()->fetchNum());
        $this->assertSame($expect[1], $res->toNextRow()->fetchNum());
    }

    public function testGetAffectedRows()
    {
        $this->refill();
        $res = self::$conn->query('UPDATE rt SET gid=0 WHERE id > 0');
        $this->assertSame(8, $res->getAffectedRows());
    }

    public function testArrayAccess()
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
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertSame($expect[0], $res[0]);
        $this->assertSame($expect[1], $res[1]);
    }

    public function testCountable()
    {
        $this->refill();
        $res = self::$conn->query('SELECT * FROM rt');
        $this->assertEquals($res->getCount(), $res->count());
        $this->assertEquals($res->getCount(), count($res));
    }

    public function testIterator()
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
        $res = self::$conn->query('SELECT * FROM rt');
        $array = array();
        foreach ($res as $key => $value) {
            $array[$key] = $value;
        }

        $this->assertSame($expect[0], $array[0]);
        $this->assertSame($expect[1], $array[1]);

        $res = self::$conn->query('SELECT * FROM rt WHERE id = 404');
        $array = array();
        foreach ($res as $key => $value) {
            $array[$key] = $value;
        }
        $this->assertEmpty($array);
    }
}
