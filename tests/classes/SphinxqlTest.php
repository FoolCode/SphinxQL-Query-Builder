<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\Connection as SphinxqlConnection;
use Foolz\Sphinxql\Expression as SphinxqlExpression;

class SphinxqlTest extends PHPUnit_Framework_TestCase
{
    private $sq = null;

    public function __construct()
    {
        Sphinxql::setConnection('default');
        Sphinxql::connect();

        $this->sq = Sphinxql::forge();

        // empty that poor db. TRUNCATE is still in beta in Sphinxsearch 2.1.1-beta
        Sphinxql::delete()
            ->from('rt')
            ->where('id', 'IN', array(1, 10, 11, 12, 13, 14, 15))
            ->execute();
    }

    public function testExpr()
    {
        $result = Sphinxql::expr('');

        $this->assertInstanceOf('Foolz\Sphinxql\Expression', $result);
        $this->assertEquals('', (string) $result);

        $result = Sphinxql::expr('* \\ Ç"" \'');

        $this->assertInstanceOf('Foolz\Sphinxql\Expression', $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }


    public function testSetVariable()
    {
        Sphinxql::setVariable('AUTOCOMMIT', 0);
        $vars = Sphinxql::variables();
        $this->assertEquals(0, $vars['autocommit']);

        Sphinxql::setVariable('AUTOCOMMIT', 1);
        $vars = Sphinxql::variables();
        $this->assertEquals(1, $vars['autocommit']);

        Sphinxql::setVariable('@foo', 1, true);
        Sphinxql::setVariable('@foo', array(0), true);
    }

    public function testTransactions()
    {
        Sphinxql::transactionBegin();
        Sphinxql::transactionRollback();
        Sphinxql::transactionBegin();
        Sphinxql::transactionCommit();
    }


    public function testShowTables()
    {
        $this->assertEquals(
            array(array('Index' => 'rt', 'Type' => 'rt')),
            Sphinxql::tables()
        );
    }


    public function testDescribe()
    {
        $describe = Sphinxql::describe('rt');
        array_shift($describe);
        $this->assertSame(
            array(
            //  array('Field' => 'id', 'Type' => 'integer'), this can be bigint on id64 sphinx
                array('Field' => 'title', 'Type' => 'field'),
                array('Field' => 'content', 'Type' => 'field'),
                array('Field' => 'gid', 'Type' => 'uint'),
            ),
            $describe
        );
    }


    /**
     * @covers \Foolz\Sphinxql\Sphinxql::compileInsert
     * @covers \Foolz\Sphinxql\Sphinxql::doInsert
     * @covers \Foolz\Sphinxql\Sphinxql::set
     * @covers \Foolz\Sphinxql\Sphinxql::value
     * @covers \Foolz\Sphinxql\Sphinxql::columns
     * @covers \Foolz\Sphinxql\Sphinxql::values
     * @covers \Foolz\Sphinxql\Sphinxql::into
     */
    public function testInsert()
    {
        $result = Sphinxql::insert()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'the story of a long test unit',
                'content' => 'once upon a time there was a foo in the bar',
                'gid' => 9001
            ))
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->execute();

        $this->assertCount(1, $result);

        Sphinxql::insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(11, 'this is a title', 'this is the content', 100)
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->execute();

        $this->assertCount(2, $result);


        Sphinxql::insert()
            ->into('rt')
            ->value('id', 12)
            ->value('title', 'simple logic')
            ->value('content', 'inside the box there was the content')
            ->value('gid', 200)
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->execute();

        $this->assertCount(3, $result);

        $res = Sphinxql::insert()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(13, 'i am getting bored', 'with all this CONTENT', 300)
            ->values(14, 'i want a vacation', 'the code is going to break sometime', 300)
            ->values(15, 'there\'s no hope in this class', 'just give up', 300)
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->execute();

        $this->assertCount(6, $result);
    }


    /**
     * @covers \Foolz\Sphinxql\Sphinxql::compile
     * @covers \Foolz\Sphinxql\Sphinxql::compileInsert
     * @covers \Foolz\Sphinxql\Sphinxql::doReplace
     * @covers \Foolz\Sphinxql\Sphinxql::set
     * @covers \Foolz\Sphinxql\Sphinxql::value
     * @covers \Foolz\Sphinxql\Sphinxql::columns
     * @covers \Foolz\Sphinxql\Sphinxql::values
     * @covers \Foolz\Sphinxql\Sphinxql::into
     */
    public function testReplace()
    {
        $res = Sphinxql::replace()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'modified',
                'content' => 'this field was modified with replace',
                'gid' => 9002
            ))
            ->execute();

        $this->assertSame(1, $res[0]);

        $result = Sphinxql::select()
            ->from('rt')
            ->where('id', '=', 10)
            ->execute();

        $this->assertSame('9002', $result[0]['gid']);

        $res = Sphinxql::replace()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(10, 'modifying the same line again', 'because i am that lazy', 9003)
            ->values(11, 'i am getting really creative with these strings', 'i\'ll need them to test MATCH!', 300)
            ->execute();

        $this->assertSame(2, $res[0]);

        $result = Sphinxql::select()
            ->from('rt')
            ->where('id', 'IN', array(10, 11))
            ->execute();

        $this->assertSame('9003', $result[0]['gid']);
        $this->assertSame('300', $result[1]['gid']);

        Sphinxql::replace()
            ->into('rt')
            ->value('id', 11)
            ->value('title', 'replacing value by value')
            ->value('content', 'i have no idea who would use this directly')
            ->value('gid', 200)
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute();

        $this->assertSame('200', $result[0]['gid']);
    }


    /**
     * @covers \Foolz\Sphinxql\Sphinxql::compileUpdate
     * @covers \Foolz\Sphinxql\Sphinxql::doUpdate
     */
    public function testUpdate()
    {
        $result = Sphinxql::update('rt')
            ->where('id', '=', 11)
            ->value('gid', 201)
            ->execute();

        $this->assertSame(1, $result[0]);

        $result = Sphinxql::update('rt')
            ->where('gid', '=', 300)
            ->value('gid', 305)
            ->execute();

        $this->assertSame(3, $result[0]);

        $result = Sphinxql::select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute();

        $this->assertSame('201', $result[0]['gid']);

        $result = Sphinxql::update('rt')
            ->where('gid', '=', 305)
            ->set(array('gid' => 304))
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->where('gid', '=', 304)
            ->execute();

        $this->assertCount(3, $result);
    }


    /**
     * @covers \Foolz\Sphinxql\Sphinxql::compileWhere
     * @covers \Foolz\Sphinxql\Sphinxql::from
     */
    public function testWhere()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->where('gid', 'BETWEEN', array(300, 400))
            ->execute();

        $this->assertCount(3, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->where('id', 'IN', array(11, 12, 13))
            ->execute();

        $this->assertCount(3, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute();

        $this->assertCount(4, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->where('gid', 304)
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute();

        $this->assertCount(4, $result);
    }


    /**
     * @covers \Foolz\Sphinxql\Sphinxql::compileMatch
     * @covers \Foolz\Sphinxql\Sphinxql::halfEscapeMatch
     */
    public function testMatch()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->match('content', 'content')
            ->execute();

        $this->assertCount(2, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->match('title', 'value')
            ->execute();

        $this->assertCount(1, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->match('title', 'value')
            ->match('content', 'directly')
            ->execute();

        $this->assertCount(1, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->match('content', 'directly | lazy', true)
            ->execute();

        $this->assertCount(2, $result);
    }


    public function testOption()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', 1)
            ->execute();

        $this->assertCount(1, $result);
    }


    public function testGroupBy()
    {
        $result = Sphinxql::select(Sphinxql::expr('@count'))
            ->from('rt')
            ->groupBy('gid')
            ->execute();

        $this->assertCount(4, $result);
        $this->assertSame('3', $result[3]['@count']);
    }


    public function testOrderBy()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->orderBy('id', 'desc')
            ->execute();

        $this->assertSame('15', $result[0]['id']);

        $result = Sphinxql::select()
            ->from('rt')
            ->orderBy('id', 'asc')
            ->execute();

        $this->assertSame('10', $result[0]['id']);
    }


    public function testWithinGroupOrderBy()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'desc')
            ->execute();

        $this->assertSame('13', $result[0]['id']);

        $result = Sphinxql::select()
            ->from('rt')
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'asc')
            ->execute();

        $this->assertSame('10', $result[0]['id']);

    }


    public function testOffset()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->offset(3)
            ->execute();

        $this->assertCount(3, $result);
    }


    public function testLimit()
    {
        $result = Sphinxql::select()
            ->from('rt')
            ->limit(3)
            ->execute();

        $this->assertCount(3, $result);

        $result = Sphinxql::select()
            ->from('rt')
            ->limit(2, 3)
            ->execute();

        $this->assertCount(3, $result);
    }


    /**
     * @covers \Foolz\Sphinxql\Sphinxql::compileDelete
     */
    public function testDelete()
    {
        Sphinxql::delete()
            ->from('rt')
            ->where('id', 'IN', array(10, 11, 12))
            ->execute();

        $result = Sphinxql::select()
            ->from('rt')
            ->execute();

        $this->assertCount(3, $result);
    }
}