<?php
use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Expression;
use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\MatchBuilder;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Tests\TestUtil;

class SphinxQLTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ConnectionBase $conn
     */
    public static $conn;

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

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public static function setUpBeforeClass(): void
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        self::$conn = $conn;

        (new SphinxQL(self::$conn))->getConnection()->query('TRUNCATE RTINDEX rt');
    }

    /**
     * @return SphinxQL
     */
    protected function createSphinxQL(): SphinxQL
    {
        return new SphinxQL(self::$conn);
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

        foreach (static::$data as $row) {
            $sq->values($row['id'], $row['gid'], $row['title'], $row['content']);
        }

        $sq->execute();
    }

    public function testExpr(): void
    {
        $result = SphinxQL::expr('');

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('', (string) $result);

        $result = SphinxQL::expr('* \\ Ç"" \'');

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testTransactions(): void
    {
        self::assertNotNull($this->createSphinxQL());
        $this->createSphinxQL()->transactionBegin();
        $this->createSphinxQL()->transactionRollback();
        $this->createSphinxQL()->transactionBegin();
        $this->createSphinxQL()->transactionCommit();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testQuery(): void
    {
        $describe = $this->createSphinxQL()
            ->query('DESCRIBE rt')
            ->execute()
            ->fetchAllAssoc();

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

        $describe = $this->createSphinxQL()->query('DESCRIBE rt');
        $result  = $describe->execute()->fetchAllAssoc();

        array_shift($result);
        $this->assertSame(
            array(
                //	array('Field' => 'id', 'Type' => 'integer'), this can be bigint on id64 sphinx
                array('Field' => 'title', 'Type' => 'field'),
                array('Field' => 'content', 'Type' => 'field'),
                array('Field' => 'gid', 'Type' => 'uint'),
            ),
            $result
        );
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testInsert(): void
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
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

        $this->assertCount(10, $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testReplace(): void
    {
        $result = $this->createSphinxQL()
            ->replace()
            ->into('rt')
            ->set(array(
                'id' => 10,
                'title' => 'modified',
                'content' => 'this field was modified with replace',
                'gid' => 9002
            ))->execute()
            ->getAffectedRows();

        $this->assertSame(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', '=', 10)
            ->execute()
            ->fetchAllAssoc();

        $this->assertEquals('9002', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->replace()
            ->into('rt')
            ->columns('id', 'title', 'content', 'gid')
            ->values(10, 'modifying the same line again', 'because i am that lazy', 9003)
            ->values(11, 'i am getting really creative with these strings', 'i\'ll need them to test MATCH!', 300)
            ->execute()
            ->getAffectedRows();

        $this->assertSame(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', 'IN', array(10, 11))
            ->execute()
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

        $this->assertEquals('200', $result[0]['gid']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testUpdate(): void
    {
        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('id', '=', 11)
            ->value('gid', 201)
            ->execute()
            ->getAffectedRows();

        $this->assertSame(1, $result);

        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('gid', '=', 300)
            ->value('gid', 305)
            ->execute()
            ->getAffectedRows();

        $this->assertSame(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', '=', 11)
            ->execute()
            ->fetchAllAssoc();

        $this->assertEquals('201', $result[0]['gid']);

        $this->createSphinxQL()
            ->update('rt')
            ->where('gid', '=', 305)
            ->set(array('gid' => 304))
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '=', 304)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(3, $result);

        self::$conn->query('ALTER TABLE rt ADD COLUMN tags MULTI');
        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('tags', 222)
            ->execute()
            ->fetchAllAssoc();
        $this->assertEmpty($result);

        $result = $this->createSphinxQL()
            ->update('rt')
            ->where('id', '=', 15)
            ->value('tags', [111,222])
            ->execute()
            ->getAffectedRows();
        $this->assertSame(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('tags', 222)
            ->execute()
            ->fetchAllAssoc();
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
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testWhere(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 'BETWEEN', array(300, 400))
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', 'IN', array(11, 12, 13))
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('id', 'NOT IN', array(11, 12))
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(6, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(6, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(6, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', '>', 300)
            ->where('id', '!=', 15)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(5, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->where('gid', '>', 200)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(1, $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testMatch(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('title', 'value')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('title', 'value')
            ->match('content', 'directly')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('*', 'directly')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match(array('title', 'content'), 'to')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'directly | lazy', true)
            ->execute()
            ->fetchAllAssoc();

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
            ->fetchAllAssoc();

        $this->assertCount(2, $result);

        $match = (new MatchBuilder($this->createSphinxQL()))
            ->field('content')
            ->match('directly')
            ->orMatch('lazy');
        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match($match)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(2, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('')
            ->compile()
            ->getCompiled();

        $this->assertEquals('SELECT * FROM rt WHERE MATCH(\'\')', $result);
    }

    public function testEscapeMatch(): void
    {
        $match = 'this MAYBE that^32 and | hi';
        $this->assertSame('this maybe that\^32 and \| hi', $this->createSphinxQL()->escapeMatch($match));
        $this->assertSame($match, $this->createSphinxQL()->escapeMatch(SphinxQL::expr($match)));
        $this->assertSame('stärkergradig \| mb', $this->createSphinxQL()->escapeMatch('stärkergradig | mb'));
    }

    public function testHalfEscapeMatch(): void
    {
        $match = 'this MAYBE that^32 and | hi';
        $this->assertSame('this maybe that\^32 and | hi', $this->createSphinxQL()->halfEscapeMatch($match));
        $this->assertSame($match, $this->createSphinxQL()->halfEscapeMatch(SphinxQL::expr($match)));
        $this->assertSame('this \- not -that | hi \-', $this->createSphinxQL()->halfEscapeMatch('this -- not -that | | hi -'));
        $this->assertSame('stärkergradig | mb', $this->createSphinxQL()->halfEscapeMatch('stärkergradig | mb'));
        $this->assertSame('"unmatched quotes"', $this->createSphinxQL()->halfEscapeMatch('"unmatched quotes'));
    }

    public function testEscapeChars(): void
    {
        $this->assertEquals(array('%' => '\%'), $this->createSphinxQL()->compileEscapeChars(array('%')));
        $this->assertEquals(array('@' => '\@'), $this->createSphinxQL()->compileEscapeChars(array('@')));

        $match = 'this MAYBE that^32 and | hi';
        $sphinxql = $this->createSphinxQL()->setFullEscapeChars(array('^'));
        $this->assertSame('this maybe that\^32 and | hi', $sphinxql->escapeMatch($match));

        $sphinxql->setHalfEscapeChars(array('|'));
        $this->assertSame('this maybe that^32 and \| hi', $sphinxql->halfEscapeMatch($match));
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testOption(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', 1)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(1, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->match('content', 'content')
            ->option('max_matches', SphinxQL::expr('1'))
            ->execute()
            ->fetchAllAssoc();

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

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testGroupBy(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select(SphinxQL::expr('count(*)'))
            ->from('rt')
            ->groupBy('gid')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(5, $result);
        $this->assertEquals('3', $result[3]['count(*)']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testHaving(): void
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

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testOrderBy(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->orderBy('id', 'desc')
            ->execute()
            ->fetchAllAssoc();

        $this->assertEquals('17', $result[0]['id']);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->orderBy('id', 'asc')
            ->execute()
            ->fetchAllAssoc();

        $this->assertEquals('10', $result[0]['id']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testWithinGroupOrderBy(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'desc')
            ->execute()
            ->fetchAllAssoc();

        $this->assertEquals('17', $result[0]['id']);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->where('gid', 500)
            ->groupBy('gid')
            ->withinGroupOrderBy('id', 'asc')
            ->execute()
            ->fetchAllAssoc();

        $this->assertEquals('16', $result[0]['id']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testGroupNBy(): void
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

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testOffset(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->offset(4)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(4, $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testLimit(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->limit(3)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(3, $result);

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->limit(2, 3)
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(3, $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testDelete(): void
    {
        $this->refill();

        $result = $this->createSphinxQL()
            ->delete()
            ->from('rt')
            ->where('id', 'IN', [11, 12, 13])
            ->match('content', 'content')
            ->execute()
            ->getAffectedRows();

        $this->assertSame(2, $result);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testQueue(): void
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

        $this->assertEquals('10', $result[0][0]['id'] ?? null);
        $this->assertEquals('1', $result[1][0]['Value'] ?? null);
        $this->assertEquals('11', $result[2][0]['id'] ?? null);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testEmptyQueue(): void
    {
        $this->expectException(SphinxQLException::class);
        $this->expectExceptionMessage('There is no Queue present to execute.');

        $this->createSphinxQL()
            ->executeBatch()
            ->getStored();
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testResetMethods(): void
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
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testSelect(): void
    {
        $this->refill();
        $result = $this->createSphinxQL()
            ->select(array('id', 'gid'))
            ->from('rt')
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
        $this->assertEquals('9003', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->select('id', 'gid')
            ->from('rt')
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
        $this->assertEquals('9003', $result[0]['gid']);

        $result = $this->createSphinxQL()
            ->select(array('id'))
            ->from('rt')
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);

        $result = $this->createSphinxQL()
            ->select('id')
            ->from('rt')
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testSubselect(): void
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
            ->fetchAllAssoc();
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
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('17', $result[0]['id']);
        $result = $query
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
        $this->assertEquals('10', $result[0]['id']);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testSetSelect(): void
    {
        $this->refill();
        $q1 = $this->createSphinxQL()
            ->select(array('id', 'gid'))
            ->from('rt');
        $q2 = clone $q1;
        $q2->setSelect(array('id'));
        $result = $q1
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $result = $q2
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);

        $q1 = $this->createSphinxQL()
            ->select('id', 'gid')
            ->from('rt');
        $q2 = clone $q1;
        $q2->setSelect('id');
        $result = $q1
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('gid', $result[0]);
        $result = $q2
            ->execute()
            ->fetchAllAssoc();
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('gid', $result[0]);
    }

    public function testGetSelect(): void
    {
        $query = $this->createSphinxQL()
            ->select('id', 'gid')
            ->from('rt');
        $this->assertEquals(array('id', 'gid'), $query->getSelect());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testFacet(): void
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

    /**
     * Issue #82
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testClosureMisuse(): void
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
