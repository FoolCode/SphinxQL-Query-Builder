<?php

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\Match;
use Foolz\SphinxQL\Tests\TestUtil;

class SphinxQLTest extends PHPUnit_Framework_TestCase
{
    public static $conn = null;

    public static $data = array (
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

    public function refill() {
        SphinxQL::create(self::$conn)->getConnection()->query('TRUNCATE RTINDEX rt');

        $sq = SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->columns('id', 'gid', 'title', 'content');

        foreach (static::$data as $row) {
            $sq->values($row['id'], $row['gid'], $row['title'], $row['content']);
        }

        $sq->execute();
    }

    public function testExpr()
    {
        $result = SphinxQL::expr('');

        $this->assertInstanceOf('Foolz\SphinxQL\Expression', $result);
        $this->assertEquals('', (string) $result);

        $result = SphinxQL::expr('* \\ Ç"" \'');

        $this->assertInstanceOf('Foolz\SphinxQL\Expression', $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::transactionBegin
     * @covers \Foolz\SphinxQL\SphinxQL::transactionCommit
     * @covers \Foolz\SphinxQL\SphinxQL::transactionRollback
     */
    public function testTransactions()
    {
        SphinxQL::create(self::$conn)->transactionBegin();
        SphinxQL::create(self::$conn)->transactionRollback();
        SphinxQL::create(self::$conn)->transactionBegin();
        SphinxQL::create(self::$conn)->transactionCommit();
    }

    public function testQuery()
    {
        $describe = SphinxQL::create(self::$conn)
            ->query('DESCRIBE rt')
            ->execute()
            ->getStored();

        array_shift($describe);
        $this->assertSame(
            array(
                //	array('Field' => 'id', 'Type' => 'integer'), this can be bigint on id64 sphinx
                array('Field' => 'title', 'Type' => 'field'),
                array('Field' => 'content', 'Type' => 'field'),
                array('Field' => 'gid', 'Type' => 'uint'),
            ),
            $describe
        );

        $describe = SphinxQL::create(self::$conn)
            ->query('DESCRIBE rt');
        $describe->execute();
        $describe = $describe
            ->getResult()
            ->getStored();

        array_shift($describe);
        $this->assertSame(
            array(
                //	array('Field' => 'id', 'Type' => 'integer'), this can be bigint on id64 sphinx
                array('Field' => 'title', 'Type' => 'field'),
                array('Field' => 'content', 'Type' => 'field'),
                array('Field' => 'gid', 'Type' => 'uint'),
            ),
            $describe
        );
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compile
     * @covers \Foolz\SphinxQL\SphinxQL::compileInsert
     * @covers \Foolz\SphinxQL\SphinxQL::compileSelect
     * @covers \Foolz\SphinxQL\SphinxQL::insert
     * @covers \Foolz\SphinxQL\SphinxQL::set
     * @covers \Foolz\SphinxQL\SphinxQL::value
     * @covers \Foolz\SphinxQL\SphinxQL::columns
     * @covers \Foolz\SphinxQL\SphinxQL::values
     * @covers \Foolz\SphinxQL\SphinxQL::into
     */
    public function testInsert()
    {
        SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'the story of a long test unit',
                'content' => 'once upon a time there was a foo in the bar',
                'gid' => 9001
            ))
            ->execute();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(11, 'this is a title', 'this is the content', 100)
            ->execute();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->value('id', 12)
            ->value('title', 'simple logic')
            ->value('content', 'inside the box there was the content')
            ->value('gid', 200)
            ->execute();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->columns(array('id', 'title', 'content', 'gid'))
            ->values(array(13, 'i am getting bored', 'with all this CONTENT', 300))
            ->values(14, 'i want a vacation', 'the code is going to break sometime', 300)
            ->values(15, 'there\'s no hope in this class', 'just give up', 300)
            ->execute();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        SphinxQL::create(self::$conn)->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(16, 'we need to test', 'selecting the best result in groups', 500)
            ->values(17, 'what is there to do', 'we need to create dummy data for tests', 500)
            ->execute();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(8, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compile
     * @covers \Foolz\SphinxQL\SphinxQL::compileInsert
     * @covers \Foolz\SphinxQL\SphinxQL::compileSelect
     * @covers \Foolz\SphinxQL\SphinxQL::replace
     * @covers \Foolz\SphinxQL\SphinxQL::set
     * @covers \Foolz\SphinxQL\SphinxQL::value
     * @covers \Foolz\SphinxQL\SphinxQL::columns
     * @covers \Foolz\SphinxQL\SphinxQL::values
     * @covers \Foolz\SphinxQL\SphinxQL::into
     */
    public function testReplace()
    {
        $result = SphinxQL::create(self::$conn)->replace()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'modified',
                'content' => 'this field was modified with replace',
                'gid' => 9002
            ))
            ->execute()
            ->getStored();

        $this->assertSame(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', '=', 10)
            ->execute()
            ->getStored();

        $this->assertEquals('9002', $result[0]['gid']);

        $result = SphinxQL::create(self::$conn)->replace()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(10, 'modifying the same line again', 'because i am that lazy', 9003)
            ->values(11, 'i am getting really creative with these strings', 'i\'ll need them to test MATCH!', 300)
            ->execute()
            ->getStored();

        $this->assertSame(2, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', 'IN', array(10, 11))
            ->execute()
            ->getStored();

        $this->assertEquals('9003', $result[0]['gid']);
        $this->assertEquals('300', $result[1]['gid']);

        SphinxQL::create(self::$conn)->replace()
            ->into('rt')
            ->value('id', 11)
            ->value('title', 'replacing value by value')
            ->value('content', 'i have no idea who would use this directly')
            ->value('gid', 200)
            ->execute();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute()
            ->getStored();

        $this->assertEquals('200', $result[0]['gid']);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compile
     * @covers \Foolz\SphinxQL\SphinxQL::compileUpdate
     * @covers \Foolz\SphinxQL\SphinxQL::compileSelect
     * @covers \Foolz\SphinxQL\SphinxQL::update
     * @covers \Foolz\SphinxQL\SphinxQL::value
     */
    public function testUpdate()
    {
        $result = SphinxQL::create(self::$conn)->update('rt')
            ->where('id', '=', 11)
            ->value('gid', 201)
            ->execute()
            ->getStored();

        $this->assertSame(1, $result);

        $result = SphinxQL::create(self::$conn)->update('rt')
            ->where('gid', '=', 300)
            ->value('gid', 305)
            ->execute()
            ->getStored();

        $this->assertSame(3, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute()
            ->getStored();

        $this->assertEquals('201', $result[0]['gid']);

        $result = SphinxQL::create(self::$conn)->update('rt')
            ->where('gid', '=', 305)
            ->set(array('gid' => 304))
            ->execute()
            ->getStored();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', '=', 304)
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        self::$conn->query('ALTER TABLE rt ADD COLUMN tags MULTI');
        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('tags', 222)
            ->execute()
            ->getStored();
        $this->assertEmpty($result);

        $result = SphinxQL::create(self::$conn)->update('rt')
            ->where('id', '=', 15)
            ->value('tags', array(111, 222))
            ->execute()
            ->getStored();
        $this->assertSame(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('tags', 222)
            ->execute()
            ->getStored();
        $this->assertEquals(
            array(
                array(
                    'id'   => '15',
                    'gid'  => '304',
                    'tags' => '111,222',
                ),
            ),
            $result
        );
        self::$conn->query('ALTER TABLE rt DROP COLUMN tags');
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compileWhere
     * @covers \Foolz\SphinxQL\SphinxQL::from
     * @covers \Foolz\SphinxQL\SphinxQL::compileFilterCondition
     */
    public function testWhere()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', 'BETWEEN', array(300, 400))
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', 'IN', array(11, 12, 13))
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', 'NOT IN', array(11, 12))
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', 304)
            ->execute()
            ->getStored();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->where('id', '!=', 15)
            ->execute()
            ->getStored();

        $this->assertCount(5, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('content', 'content')
            ->where('gid', '>', 200)
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::match
     * @covers \Foolz\SphinxQL\SphinxQL::compileMatch
     * @covers \Foolz\SphinxQL\SphinxQL::halfEscapeMatch
     */
    public function testMatch()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('content', 'content')
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('title', 'value')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('title', 'value')
            ->match('content', 'directly')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('*', 'directly')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match(array('title', 'content'), 'to')
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('content', 'directly | lazy', true)
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match(function ($m) {
                $m->field('content')
                    ->match('directly')
                    ->orMatch('lazy');
            })
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $match = Match::create(SphinxQL::create(self::$conn))
            ->field('content')
            ->match('directly')
            ->orMatch('lazy');
        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match($match)
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);
    }

    public function testEscapeMatch()
    {
        $match = 'this MAYBE that^32 and | hi';
        $this->assertSame('this maybe that\^32 and \| hi', SphinxQL::create(self::$conn)->escapeMatch($match));
        $this->assertSame($match, SphinxQL::create(self::$conn)->escapeMatch(SphinxQL::expr($match)));
        $this->assertSame('stärkergradig \| mb', SphinxQL::create(self::$conn)->escapeMatch('stärkergradig | mb'));
    }

    public function testHalfEscapeMatch()
    {
        $match = 'this MAYBE that^32 and | hi';
        $this->assertSame('this maybe that\^32 and | hi', SphinxQL::create(self::$conn)->halfEscapeMatch($match));
        $this->assertSame($match, SphinxQL::create(self::$conn)->halfEscapeMatch(SphinxQL::expr($match)));
        $this->assertSame('this \- not -that | hi \-', SphinxQL::create(self::$conn)->halfEscapeMatch('this -- not -that | | hi -'));
        $this->assertSame('stärkergradig | mb', SphinxQL::create(self::$conn)->halfEscapeMatch('stärkergradig | mb'));
        $this->assertSame('"unmatched quotes"', SphinxQL::create(self::$conn)->halfEscapeMatch('"unmatched quotes'));
    }

    /**
    * @covers \Foolz\SphinxQL\SphinxQL::setFullEscapeChars
    * @covers \Foolz\SphinxQL\SphinxQL::setHalfEscapeChars
    * @covers \Foolz\SphinxQL\SphinxQL::compileEscapeChars
    */
    public function testEscapeChars()
    {
        $this->assertEquals(array('%' => '\%'), SphinxQL::create(self::$conn)->compileEscapeChars(array('%')));
        $this->assertEquals(array('@' => '\@'), SphinxQL::create(self::$conn)->compileEscapeChars(array('@')));

        $match = 'this MAYBE that^32 and | hi';
        $sphinxql = SphinxQL::create(self::$conn)->setFullEscapeChars(array('^'));
        $this->assertSame('this maybe that\^32 and | hi', $sphinxql->escapeMatch($match));

        $sphinxql->setHalfEscapeChars(array('|'));
        $this->assertSame('this maybe that^32 and \| hi', $sphinxql->halfEscapeMatch($match));
    }

    public function testOption()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', 1)
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', SphinxQL::expr('1'))
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->option('comment', 'this should be quoted')
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt OPTION comment = \'this should be quoted\'', $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->option('field_weights', SphinxQL::expr('(content=50)'))
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt OPTION field_weights = (content=50)', $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->option('field_weights', array(
                'title'   => 80,
                'content' => 35,
                'tags'    => 92,
            ))
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt OPTION field_weights = (title=80, content=35, tags=92)', $result);
    }

    public function testGroupBy()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select(SphinxQL::expr('count(*)'))
            ->from('rt')
            ->groupBy('gid')
            ->execute()
            ->getStored();

        $this->assertCount(5, $result);
        $this->assertEquals('3', $result[3]['count(*)']);
    }

    public function testHaving()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select(SphinxQL::expr('count(*) as cnt'))
            ->from('rt')
            ->groupBy('gid')
            ->having('cnt', '>', 1)
            ->execute();

        $this->assertCount(2, $result);
        $this->assertEquals('2', $result[1]['cnt']);

        $result = SphinxQL::create(self::$conn)->select(SphinxQL::expr('count(*) as cnt'))
            ->from('rt')
            ->groupBy('gid')
            ->having('gid', 304)
            ->execute();

        $this->assertCount(1, $result);
    }

    public function testOrderBy()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->orderBy('id', 'desc')
            ->execute()
            ->getStored();

        $this->assertEquals('17', $result[0]['id']);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->orderBy('id', 'asc')
            ->execute()
            ->getStored();

        $this->assertEquals('10', $result[0]['id']);
    }

    public function testWithinGroupOrderBy()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'desc')
            ->execute()
            ->getStored();

        $this->assertEquals('17', $result[0]['id']);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'asc')
            ->execute()
            ->getStored();

        $this->assertEquals('16', $result[0]['id']);
    }

    public function testOffset()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->offset(4)
            ->execute()
            ->getStored();

        $this->assertCount(4, $result);
    }

    public function testLimit()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->limit(3)
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->limit(2, 3)
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compile
     * @covers \Foolz\SphinxQL\SphinxQL::compileDelete
     * @covers \Foolz\SphinxQL\SphinxQL::delete
     */
    public function testDelete()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)->delete()
            ->from('rt')
            ->where('id', 'IN', array(10, 11, 12))
            ->execute()
            ->getStored();

        $this->assertSame(3, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::executeBatch
     * @covers \Foolz\SphinxQL\SphinxQL::enqueue
     * @covers \Foolz\SphinxQL\SphinxQL::getQueue
     * @covers \Foolz\SphinxQL\SphinxQL::getQueuePrev
     * @covers \Foolz\SphinxQL\SphinxQL::setQueuePrev
     */
    public function testQueue()
    {
        $this->refill();

        $result = SphinxQL::create(self::$conn)
            ->select()
            ->from('rt')
            ->where('gid', 9003)
            ->enqueue(Helper::create(self::$conn)->showMeta())
            ->enqueue()
            ->select()
            ->from('rt')
            ->where('gid', 201)
            ->executeBatch()
            ->getStored();

        $this->assertEquals('10', $result[0][0]['id']);
        $this->assertEquals('1', $result[1][0]['Value']);
        $this->assertEquals('11', $result[2][0]['id']);
    }

    /**
     * @expectedException        Foolz\SphinxQL\Exception\SphinxQLException
     * @expectedExceptionMessage There is no Queue present to execute.
     */
    public function testEmptyQueue()
    {
        SphinxQL::create(self::$conn)
            ->executeBatch()
            ->getStored();
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::resetWhere
     * @covers \Foolz\SphinxQL\SphinxQL::resetMatch
     * @covers \Foolz\SphinxQL\SphinxQL::resetGroupBy
     * @covers \Foolz\SphinxQL\SphinxQL::resetWithinGroupOrderBy
     * @covers \Foolz\SphinxQL\SphinxQL::resetOptions
     * @covers \Foolz\SphinxQL\SphinxQL::resetHaving
     * @covers \Foolz\SphinxQL\SphinxQL::resetOrderBy
     */
    public function testResetMethods()
    {
        $result = SphinxQL::create(self::$conn)->select()
            ->from('rt')
            ->where('id', 'IN', array(10, 11))
            ->resetWhere()
            ->match('title', 'value')
            ->resetMatch()
            ->groupBy('gid')
            ->resetGroupBy()
            ->having('gid', '=', '304')
            ->resetHaving()
            ->withinGroupOrderBy('id', 'desc')
            ->resetWithinGroupOrderBy()
            ->option('comment', 'this should be quoted')
            ->resetOptions()
            ->orderBy('id', 'desc')
            ->resetOrderBy()
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt', $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::select
     */
    public function testSelect()
    {
        $this->refill();
        $result = SphinxQL::create(self::$conn)
            ->select(array('id', 'gid'))
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
        $this->assertEquals('9003', $result[0]['gid']);

        $result = SphinxQL::create(self::$conn)
            ->select('id', 'gid')
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
        $this->assertEquals('9003', $result[0]['gid']);

        $result = SphinxQL::create(self::$conn)
            ->select(array('id'))
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);

        $result = SphinxQL::create(self::$conn)
            ->select('id')
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
    }

    public function testSubselect()
    {
        $this->refill();
        $query = SphinxQL::create(self::$conn)
            ->select()
            ->from(function ($q) {
                $q->select('id')
                    ->from('rt')
                    ->orderBy('id', 'DESC');
            })
            ->orderBy('id', 'ASC');
        $this->assertEquals(
            'SELECT * FROM (SELECT id FROM rt ORDER BY id DESC) ORDER BY id ASC',
            $query->compile()->getCompiled()
        );
        $result = $query
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);

        $subquery = SphinxQL::create(self::$conn)
            ->select('id')
            ->from('rt')
            ->orderBy('id', 'DESC');
        $query = SphinxQL::create(self::$conn)
            ->select()
            ->from($subquery)
            ->orderBy('id', 'ASC');
        $this->assertEquals(
            'SELECT id FROM rt ORDER BY id DESC',
            $subquery->compile()->getCompiled()
        );
        $this->assertEquals(
            'SELECT * FROM (SELECT id FROM rt ORDER BY id DESC) ORDER BY id ASC',
            $query->compile()->getCompiled()
        );
        $result = $subquery
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('17', $result[0]['id']);
        $result = $query
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::facet
     * @covers \Foolz\SphinxQL\SphinxQL::compileSelect
     */
    public function testFacet()
    {
        $this->refill();

        // test both setting and not setting the connection
        foreach (array(self::$conn, null) as $conn) {
            $result = SphinxQL::create(self::$conn)
                ->select()
                ->from('rt')
                ->facet(Facet::create($conn)
                    ->facetFunction('INTERVAL', array('gid', 300, 600))
                    ->orderByFunction('FACET', '', 'ASC'))
                ->executeBatch()
                ->getStored();

            $this->assertArrayHasKey('id', $result[0][0]);
            $this->assertArrayHasKey('interval(gid,300,600)', $result[1][0]);
            $this->assertArrayHasKey('count(*)', $result[1][0]);

            $this->assertEquals('2', $result[1][0]['count(*)']);
            $this->assertEquals('5', $result[1][1]['count(*)']);
            $this->assertEquals('1', $result[1][2]['count(*)']);

            $result = SphinxQL::create(self::$conn)
                ->select()
                ->from('rt')
                ->facet(Facet::create($conn)
                    ->facet(array('gid'))
                    ->orderBy('gid', 'ASC'))
                ->executeBatch()
                ->getStored();

            $this->assertArrayHasKey('id', $result[0][0]);
            $this->assertArrayHasKey('gid', $result[1][0]);
            $this->assertArrayHasKey('count(*)', $result[1][0]);

            $this->assertEquals('1', $result[1][0]['count(*)']);
            $this->assertEquals('200', $result[1][0]['gid']);
            $this->assertEquals('3', $result[1][2]['count(*)']);
            $this->assertEquals('2', $result[1][3]['count(*)']);
        }
    }

    // issue #82
    public function testClosureMisuse()
    {
        $query = SphinxQL::create(self::$conn)
            ->select()
            ->from('strlen')
            ->orderBy('id', 'ASC');
        $this->assertEquals(
            'SELECT * FROM strlen ORDER BY id ASC',
            $query->compile()->getCompiled()
        );

        $query = SphinxQL::create(self::$conn)
            ->select()
            ->from('rt')
            ->match('strlen', 'value');
        $this->assertEquals(
            "SELECT * FROM rt WHERE MATCH('(@strlen value)')",
            $query->compile()->getCompiled()
        );
    }
}
