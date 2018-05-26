<?php

use Foolz\SphinxQL\Expression;
use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\Match;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Tests\TestUtil;

class SphinxQLTest extends \PHPUnit\Framework\TestCase
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

        (new SphinxQL(self::$conn))->getConnection()->query('TRUNCATE RTINDEX rt');
    }

    /**
     * @return SphinxQL
     */
    protected function createSphinxQL()
    {
        return new SphinxQL(self::$conn);
    }

    public function refill()
    {
        $this->createSphinxQL()->getConnection()->query('TRUNCATE RTINDEX rt');

        $sq = $this->createSphinxQL()
            ->insert()
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

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('', (string) $result);

        $result = SphinxQL::expr('* \\ Ç"" \'');

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::transactionBegin
     * @covers \Foolz\SphinxQL\SphinxQL::transactionCommit
     * @covers \Foolz\SphinxQL\SphinxQL::transactionRollback
     */
    public function testTransactions()
    {
        $this->createSphinxQL()->transactionBegin();
        $this->createSphinxQL()->transactionRollback();
        $this->createSphinxQL()->transactionBegin();
        $this->createSphinxQL()->transactionCommit();
    }

    public function testQuery()
    {
        $describe = $this->createSphinxQL()
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

        $describe = $this->createSphinxQL()
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
        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'the story of a long test unit',
                'content' => 'once upon a time there was a foo in the bar',
                'gid' => 9001
            ))
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(11, 'this is a title', 'this is the content', 100)
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->value('id', 12)
            ->value('title', 'simple logic')
            ->value('content', 'inside the box there was the content')
            ->value('gid', 200)
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->columns(array('id', 'title', 'content', 'gid'))
            ->values(array(13, 'i am getting bored', 'with all this CONTENT', 300))
            ->values(14, 'i want a vacation', 'the code is going to break sometime', 300)
            ->values(15, 'there\'s no hope in this class', 'just give up', 300)
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(16, 'we need to test', 'selecting the best result in groups', 500)
            ->values(17, 'what is there to do', 'we need to create dummy data for tests', 500)
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(8, $result);

        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->set(array(
                'id' => 18,
                'title' => 'a multi set test',
                'content' => 'has text',
                'gid' => 9002
            ))
            ->set(array(
                'id' => 19,
                'title' => 'and a',
                'content' => 'second set call',
                'gid' => 9003
            ))
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(10, $result);

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
        $result = $this->createSphinxQL()
            ->replace()
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

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', '=', 10)
            ->execute()
            ->getStored();

        $this->assertEquals('9002', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->replace()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(10, 'modifying the same line again', 'because i am that lazy', 9003)
            ->values(11, 'i am getting really creative with these strings', 'i\'ll need them to test MATCH!', 300)
            ->execute()
            ->getStored();

        $this->assertSame(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', 'IN', array(10, 11))
            ->execute()
            ->getStored();

        $this->assertEquals('9003', $result[0]['gid']);
        $this->assertEquals('300', $result[1]['gid']);

        $this->createSphinxQL()
            ->replace()
            ->into('rt')
            ->value('id', 11)
            ->value('title', 'replacing value by value')
            ->value('content', 'i have no idea who would use this directly')
            ->value('gid', 200)
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
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
        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('id', '=', 11)
            ->value('gid', 201)
            ->execute()
            ->getStored();

        $this->assertSame(1, $result);

        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('gid', '=', 300)
            ->value('gid', 305)
            ->execute()
            ->getStored();

        $this->assertSame(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute()
            ->getStored();

        $this->assertEquals('201', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('gid', '=', 305)
            ->set(array('gid' => 304))
            ->execute()
            ->getStored();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '=', 304)
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        self::$conn->query('ALTER TABLE rt ADD COLUMN tags MULTI');
        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('tags', 222)
            ->execute()
            ->getStored();
        $this->assertEmpty($result);

        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('id', '=', 15)
            ->value('tags', array(111, 222))
            ->execute()
            ->getStored();
        $this->assertSame(1, $result);

        $result = $this->createSphinxQL()
            ->select()
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

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 'BETWEEN', array(300, 400))
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', 'IN', array(11, 12, 13))
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', 'NOT IN', array(11, 12))
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 304)
            ->execute()
            ->getStored();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute()
            ->getStored();

        $this->assertCount(6, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->where('id', '!=', 15)
            ->execute()
            ->getStored();

        $this->assertCount(5, $result);

        $result = $this->createSphinxQL()
            ->select()
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

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('title', 'value')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('title', 'value')
            ->match('content', 'directly')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('*', 'directly')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match(array('title', 'content'), 'to')
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'directly | lazy', true)
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match(function ($m) {
                $m->field('content')
                    ->match('directly')
                    ->orMatch('lazy');
            })
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $match = (new Match($this->createSphinxQL()))
            ->field('content')
            ->match('directly')
            ->orMatch('lazy');
        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match($match)
            ->execute()
            ->getStored();

        $this->assertCount(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('')
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt WHERE MATCH(\'\')', $result);
    }

    public function testEscapeMatch()
    {
        $match = 'this MAYBE that^32 and | hi';
        $this->assertSame('this maybe that\^32 and \| hi', $this->createSphinxQL()->escapeMatch($match));
        $this->assertSame($match, $this->createSphinxQL()->escapeMatch(SphinxQL::expr($match)));
        $this->assertSame('stärkergradig \| mb', $this->createSphinxQL()->escapeMatch('stärkergradig | mb'));
    }

    public function testHalfEscapeMatch()
    {
        $match = 'this MAYBE that^32 and | hi';
        $this->assertSame('this maybe that\^32 and | hi', $this->createSphinxQL()->halfEscapeMatch($match));
        $this->assertSame($match, $this->createSphinxQL()->halfEscapeMatch(SphinxQL::expr($match)));
        $this->assertSame('this \- not -that | hi \-', $this->createSphinxQL()->halfEscapeMatch('this -- not -that | | hi -'));
        $this->assertSame('stärkergradig | mb', $this->createSphinxQL()->halfEscapeMatch('stärkergradig | mb'));
        $this->assertSame('"unmatched quotes"', $this->createSphinxQL()->halfEscapeMatch('"unmatched quotes'));
    }

    /**
    * @covers \Foolz\SphinxQL\SphinxQL::setFullEscapeChars
    * @covers \Foolz\SphinxQL\SphinxQL::setHalfEscapeChars
    * @covers \Foolz\SphinxQL\SphinxQL::compileEscapeChars
    */
    public function testEscapeChars()
    {
        $this->assertEquals(array('%' => '\%'), $this->createSphinxQL()->compileEscapeChars(array('%')));
        $this->assertEquals(array('@' => '\@'), $this->createSphinxQL()->compileEscapeChars(array('@')));

        $match = 'this MAYBE that^32 and | hi';
        $sphinxql = $this->createSphinxQL()->setFullEscapeChars(array('^'));
        $this->assertSame('this maybe that\^32 and | hi', $sphinxql->escapeMatch($match));

        $sphinxql->setHalfEscapeChars(array('|'));
        $this->assertSame('this maybe that^32 and \| hi', $sphinxql->halfEscapeMatch($match));
    }

    public function testOption()
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', 1)
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', SphinxQL::expr('1'))
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->option('comment', 'this should be quoted')
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt OPTION comment = \'this should be quoted\'', $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->option('field_weights', SphinxQL::expr('(content=50)'))
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt OPTION field_weights = (content=50)', $result);

        $result = $this->createSphinxQL()
            ->select()
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

        $result = $this->createSphinxQL()
            ->select(SphinxQL::expr('count(*)'))
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

        $result = $this->createSphinxQL()
            ->select(SphinxQL::expr('count(*) as cnt'))
            ->from('rt')
            ->groupBy('gid')
            ->having('cnt', '>', 1)
            ->execute();

        $this->assertCount(2, $result);
        $this->assertEquals('2', $result[1]['cnt']);

        $result = $this->createSphinxQL()
            ->select(SphinxQL::expr('count(*) as cnt'), SphinxQL::expr('GROUPBY() gd'))
            ->from('rt')
            ->groupBy('gid')
            ->having('gd', 304)
            ->execute();

        $this->assertCount(1, $result);
    }

    public function testOrderBy()
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->orderBy('id', 'desc')
            ->execute()
            ->getStored();

        $this->assertEquals('17', $result[0]['id']);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->orderBy('id', 'asc')
            ->execute()
            ->getStored();

        $this->assertEquals('10', $result[0]['id']);
    }

    public function testWithinGroupOrderBy()
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'desc')
            ->execute()
            ->getStored();

        $this->assertEquals('17', $result[0]['id']);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'asc')
            ->execute()
            ->getStored();

        $this->assertEquals('16', $result[0]['id']);
    }

    public function testGroupNBy()
    {
        $query = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->groupBy('gid');
        $this->assertEquals(
            'SELECT * FROM rt GROUP BY gid',
            $query->compile()->getCompiled()
        );

        $query->groupNBy(3);
        $this->assertEquals(
            'SELECT * FROM rt GROUP 3 BY gid',
            $query->compile()->getCompiled()
        );

        $query->resetGroupBy();
        $this->assertEquals(
            'SELECT * FROM rt',
            $query->compile()->getCompiled()
        );

        $query->groupBy('gid');
        $this->assertEquals(
            'SELECT * FROM rt GROUP BY gid',
            $query->compile()->getCompiled()
        );

        $query->resetGroupBy()
            ->groupNBy(3);
        $this->assertEquals(
            'SELECT * FROM rt',
            $query->compile()->getCompiled()
        );
    }

    public function testOffset()
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->offset(4)
            ->execute()
            ->getStored();

        $this->assertCount(4, $result);
    }

    public function testLimit()
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->limit(3)
            ->execute()
            ->getStored();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
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

        $result = $this->createSphinxQL()
            ->delete()
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

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 9003)
            ->enqueue((new Helper(self::$conn))->showMeta())
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
        $this->createSphinxQL()
            ->executeBatch()
            ->getStored();
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::resetWhere
     * @covers \Foolz\SphinxQL\SphinxQL::resetMatch
     * @covers \Foolz\SphinxQL\SphinxQL::resetGroupBy
     * @covers \Foolz\SphinxQL\SphinxQL::resetWithinGroupOrderBy
     * @covers \Foolz\SphinxQL\SphinxQL::resetOptions
     * @covers \Foolz\SphinxQL\SphinxQL::resetFacets
     * @covers \Foolz\SphinxQL\SphinxQL::resetHaving
     * @covers \Foolz\SphinxQL\SphinxQL::resetOrderBy
     */
    public function testResetMethods()
    {
        $result = $this->createSphinxQL()
            ->select()
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
            ->facet(
                (new Facet(self::$conn))->facet(array('gid'))
            )
            ->resetFacets()
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
        $result = $this->createSphinxQL()
            ->select(array('id', 'gid'))
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
        $this->assertEquals('9003', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->select('id', 'gid')
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
        $this->assertEquals('9003', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->select(array('id'))
            ->from('rt')
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);

        $result = $this->createSphinxQL()
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
        $query = $this->createSphinxQL()
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

        $subquery = $this->createSphinxQL()
            ->select('id')
            ->from('rt')
            ->orderBy('id', 'DESC');
        $query = $this->createSphinxQL()
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
     * @covers \Foolz\SphinxQL\SphinxQL::setSelect
     */
    public function testSetSelect()
    {
        $this->refill();
        $q1 = $this->createSphinxQL()
            ->select(array('id', 'gid'))
            ->from('rt');
        $q2 = clone $q1;
        $q2->setSelect(array('id'));
        $result = $q1
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $result = $q2
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);

        $q1 = $this->createSphinxQL()
            ->select('id', 'gid')
            ->from('rt');
        $q2 = clone $q1;
        $q2->setSelect('id');
        $result = $q1
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $result = $q2
            ->execute()
            ->getStored();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::getSelect
     */
    public function testGetSelect()
    {
        $query = $this->createSphinxQL()
            ->select('id', 'gid')
            ->from('rt');
        $this->assertEquals(array('id', 'gid'), $query->getSelect());
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
            $result = $this->createSphinxQL()
                ->select()
                ->from('rt')
                ->facet((new Facet($conn))
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

            $result = $this->createSphinxQL()
                ->select()
                ->from('rt')
                ->facet((new Facet($conn))
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
        $query = $this->createSphinxQL()
            ->select()
            ->from('strlen')
            ->orderBy('id', 'ASC');
        $this->assertEquals(
            'SELECT * FROM strlen ORDER BY id ASC',
            $query->compile()->getCompiled()
        );

        $query = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('strlen', 'value');
        $this->assertEquals(
            "SELECT * FROM rt WHERE MATCH('(@strlen value)')",
            $query->compile()->getCompiled()
        );
    }
}
