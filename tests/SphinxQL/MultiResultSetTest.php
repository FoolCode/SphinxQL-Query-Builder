<?php

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Drivers\Mysqli\Connection;

class MultiResultSetTest extends PHPUnit_Framework_TestCase
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
        $conn = new Connection();
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

    public function testIsMultiResultSet()
    {
        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $this->assertInstanceOf('\Foolz\Sphinxql\Drivers\MultiResultSetInterface', $res);
        $res->flush();
    }

    public function testGetNextSet()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));

        $set = $res->toNextSet()->getSet();
        $this->assertInstanceOf('\Foolz\Sphinxql\Drivers\ResultSetInterface', $set);
        $set = $res->toNextSet()->getSet();
        $this->assertInstanceOf('\Foolz\Sphinxql\Drivers\ResultSetInterface', $set);
    }

    /**
     * @expectedException \Foolz\SphinxQL\Drivers\ResultSetException
     */
    public function testGetNextSetException()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $res->toNextSet();
        $res->toNextSet();
        $res->toNextSet();
    }

    public function testHasNextSet()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $res->toNextSet();
        $this->assertTrue($res->hasNextSet());
        $res->toNextSet();
        $this->assertFalse($res->hasNextSet());
    }

    public function testCount()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $this->assertSame(2, $res->getCount());
        $res->flush();
    }

    public function testFlush()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $res->flush();
        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $res->flush();
    }

    public function testStore()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $res->store();
        $stored = $res->getStored();
        $this->assertCount(2, $stored);
        $this->assertInstanceOf('\Foolz\SphinxQL\Drivers\ResultSetInterface', $stored[0]);
        $all = $stored[0]->fetchAllAssoc();
        $this->assertEquals(8, $all[0]['count(*)']);
        $res->flush();
    }

    public function testArrayAccess()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));

        $this->assertEquals(8, $res[0][0]['count(*)']);
        $this->assertCount(2, $res);

        $res->flush();
    }

    public function testIterator()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));

        $array = array();
        foreach($res as $key => $value) {
            $array[$key] = $value;
        }

        $this->assertCount(2, $array);
    }

    public function testIteratorStored()
    {
        $this->refill();

        $res = self::$conn->multiQuery(array('SELECT COUNT(*) FROM rt', 'SHOW META'));
        $res->store();
        $array = array();
        foreach($res as $key => $value) {
            $array[$key] = $value;
        }

        $this->assertCount(2, $array);

        foreach($res as $key => $value) {
            $array[$key] = $value;
        }

        $this->assertCount(2, $array);
    }
}
