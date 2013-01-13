<?php

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Connection as SphinxConnection;

class SphinxQLTest extends PHPUnit_Framework_TestCase
{
    private $sq = null;

    public function __construct()
    {
        $conn = new SphinxConnection();
        $conn->setConnectionParams('127.0.0.1', 9307);

        SphinxQL::forge($conn);

        // empty that poor db. TRUNCATE is still in beta in Sphinxsearch 2.1.1-beta
        SphinxQL::forge()->delete()
            ->from('rt')
            ->where('id', 'IN', array(1, 10, 11, 12, 13, 14, 15, 16, 17))
            ->execute();
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

    public function testSetVariable()
    {
        SphinxQL::forge()->setVariable('AUTOCOMMIT', 0);
        $vars = SphinxQL::forge()->variables();
        $this->assertEquals(0, $vars['autocommit']);

        SphinxQL::forge()->setVariable('AUTOCOMMIT', 1);
        $vars = SphinxQL::forge()->variables();
        $this->assertEquals(1, $vars['autocommit']);

        SphinxQL::forge()->setVariable('@foo', 1, true);
        SphinxQL::forge()->setVariable('@foo', array(0), true);
    }

    public function testTransactions()
    {
        SphinxQL::forge()->transactionBegin();
        SphinxQL::forge()->transactionRollback();
        SphinxQL::forge()->transactionBegin();
        SphinxQL::forge()->transactionCommit();
    }

    public function testShowTables()
    {
        $this->assertEquals(
            array(array('Index' => 'rt', 'Type' => 'rt')),
            SphinxQL::forge()->tables()
        );
    }

    public function testDescribe()
    {
        $describe = SphinxQL::forge()->describe('rt');
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
     * @covers \Foolz\SphinxQL\SphinxQL::compileInsert
     * @covers \Foolz\SphinxQL\SphinxQL::doInsert
     * @covers \Foolz\SphinxQL\SphinxQL::set
     * @covers \Foolz\SphinxQL\SphinxQL::value
     * @covers \Foolz\SphinxQL\SphinxQL::columns
     * @covers \Foolz\SphinxQL\SphinxQL::values
     * @covers \Foolz\SphinxQL\SphinxQL::into
     */
    public function testInsert()
    {
        SphinxQL::forge()->insert()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'the story of a long test unit',
                'content' => 'once upon a time there was a foo in the bar',
                'gid' => 9001
            ))
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->execute();

        $this->assertCount(1, $result);

        SphinxQL::forge()->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(11, 'this is a title', 'this is the content', 100)
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->execute();

        $this->assertCount(2, $result);

        SphinxQL::forge()->insert()
            ->into('rt')
            ->value('id', 12)
            ->value('title', 'simple logic')
            ->value('content', 'inside the box there was the content')
            ->value('gid', 200)
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->execute();

        $this->assertCount(3, $result);

        SphinxQL::forge()->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(13, 'i am getting bored', 'with all this CONTENT', 300)
            ->values(14, 'i want a vacation', 'the code is going to break sometime', 300)
            ->values(15, 'there\'s no hope in this class', 'just give up', 300)
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->execute();

        $this->assertCount(6, $result);

        SphinxQL::forge()->insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(16, 'we need to test', 'selecting the best result in groups', 500)
            ->values(17, 'what is there to do', 'we need to create dummy data for tests', 500)
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->execute();

        $this->assertCount(8, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compile
     * @covers \Foolz\SphinxQL\SphinxQL::compileInsert
     * @covers \Foolz\SphinxQL\SphinxQL::doReplace
     * @covers \Foolz\SphinxQL\SphinxQL::set
     * @covers \Foolz\SphinxQL\SphinxQL::value
     * @covers \Foolz\SphinxQL\SphinxQL::columns
     * @covers \Foolz\SphinxQL\SphinxQL::values
     * @covers \Foolz\SphinxQL\SphinxQL::into
     */
    public function testReplace()
    {
        $result = SphinxQL::forge()->replace()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'modified',
                'content' => 'this field was modified with replace',
                'gid' => 9002
            ))
            ->execute();

        $this->assertSame(1, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('id', '=', 10)
            ->execute();

        $this->assertSame('9002', $result[0]['gid']);

        $result = SphinxQL::forge()->replace()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(10, 'modifying the same line again', 'because i am that lazy', 9003)
            ->values(11, 'i am getting really creative with these strings', 'i\'ll need them to test MATCH!', 300)
            ->execute();

        $this->assertSame(2, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('id', 'IN', array(10, 11))
            ->execute();

        $this->assertSame('9003', $result[0]['gid']);
        $this->assertSame('300', $result[1]['gid']);

        SphinxQL::forge()->replace()
            ->into('rt')
            ->value('id', 11)
            ->value('title', 'replacing value by value')
            ->value('content', 'i have no idea who would use this directly')
            ->value('gid', 200)
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute();

        $this->assertSame('200', $result[0]['gid']);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compileUpdate
     * @covers \Foolz\SphinxQL\SphinxQL::doUpdate
     */
    public function testUpdate()
    {
        $result = SphinxQL::forge()->update('rt')
            ->where('id', '=', 11)
            ->value('gid', 201)
            ->execute();

        $this->assertSame(1, $result);

        $result = SphinxQL::forge()->update('rt')
            ->where('gid', '=', 300)
            ->value('gid', 305)
            ->execute();

        $this->assertSame(3, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute();

        $this->assertSame('201', $result[0]['gid']);

        $result = SphinxQL::forge()->update('rt')
            ->where('gid', '=', 305)
            ->set(array('gid' => 304))
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', '=', 304)
            ->execute();

        $this->assertCount(3, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compileWhere
     * @covers \Foolz\SphinxQL\SphinxQL::from
     */
    public function testWhere()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', 'BETWEEN', array(300, 400))
            ->execute();

        $this->assertCount(3, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('id', 'IN', array(11, 12, 13))
            ->execute();

        $this->assertCount(3, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute();

        $this->assertCount(6, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', 304)
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute();

        $this->assertCount(6, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compileMatch
     * @covers \Foolz\SphinxQL\SphinxQL::halfEscapeMatch
     */
    public function testMatch()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->match('content', 'content')
            ->execute();

        $this->assertCount(2, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->match('title', 'value')
            ->execute();

        $this->assertCount(1, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->match('title', 'value')
            ->match('content', 'directly')
            ->execute();

        $this->assertCount(1, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->match('content', 'directly | lazy', true)
            ->execute();

        $this->assertCount(2, $result);
    }

    public function testOption()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', 1)
            ->execute();

        $this->assertCount(1, $result);
    }

    public function testGroupBy()
    {
        $result = SphinxQL::forge()->select(SphinxQL::expr('@count'))
            ->from('rt')
            ->groupBy('gid')
            ->execute();

        $this->assertCount(5, $result);
        $this->assertSame('3', $result[3]['@count']);
    }

    public function testOrderBy()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->orderBy('id', 'desc')
            ->execute();

        $this->assertSame('17', $result[0]['id']);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->orderBy('id', 'asc')
            ->execute();

        $this->assertSame('10', $result[0]['id']);
    }

    public function testWithinGroupOrderBy()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'desc')
            ->execute();

        $this->assertSame('17', $result[0]['id']);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'asc')
            ->execute();

        $this->assertSame('16', $result[0]['id']);
    }

    public function testOffset()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->offset(4)
            ->execute();

        $this->assertCount(4, $result);
    }

    public function testLimit()
    {
        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->limit(3)
            ->execute();

        $this->assertCount(3, $result);

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->limit(2, 3)
            ->execute();

        $this->assertCount(3, $result);
    }

    /**
     * @covers \Foolz\SphinxQL\SphinxQL::compileDelete
     */
    public function testDelete()
    {
        SphinxQL::forge()->delete()
            ->from('rt')
            ->where('id', 'IN', array(10, 11, 12))
            ->execute();

        $result = SphinxQL::forge()->select()
            ->from('rt')
            ->execute();

        $this->assertCount(5, $result);
    }

	/**
	 * @covers \Foolz\SphinxQL\SphinxQL::enqueue
	 * @covers \Foolz\SphinxQL\SphinxQL::getQueue
	 */
	public function testQueue()
	{

	}
}