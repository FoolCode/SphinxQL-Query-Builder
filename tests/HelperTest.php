<?php
namespace Foolz\SphinxQL\Tests;

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{

    /**
     * @var ConnectionInterface
     */
    public static $connection;

    public static function setUpBeforeClass(): void
    {
        self::$connection = TestUtil::getConnectionDriver();
        self::$connection->setParam('port', 9307);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    protected function setUp(): void
    {
        $this->createSphinxQL()->query('TRUNCATE RTINDEX rt')->execute();
    }

    /**
     * @return SphinxQL
     */
    protected function createSphinxQL(): SphinxQL
    {
        return new SphinxQL(self::$connection);
    }

    /**
     * @return Helper
     */
    protected function createHelper(): Helper
    {
        return new Helper(self::$connection);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testShowTables(): void
    {
        $this->assertEquals([
            [
                'Index'	=> 'rt',
                'Type'	=> 'rt',
            ]
        ], $this->createHelper()->showTables('rt')->execute()->fetchAllAssoc());
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testDescribe(): void
    {
        $describe = $this->createHelper()->describe('rt')->execute()->fetchAllAssoc();
        array_shift($describe);

        $expect = (TestUtil::getSearchBuild()==='SPHINX3')?[
            [
                'Field'			=> 'title',
                'Type'			=> 'field',
                'Properties'	=> 'indexed',
                'Key'			=> '',
            ],
            [
                'Field'			=> 'content',
                'Type'			=> 'field',
                'Properties'	=> 'indexed',
                'Key'			=> '',
            ],
            [
                'Field'			=> 'gid',
                'Type'			=> 'uint',
                'Properties'	=> '',
                'Key'			=> '',
            ],
        ]:[
            [
                'Field'			=> 'title',
                'Type'			=> 'field',
            ],
            [
                'Field'			=> 'content',
                'Type'			=> 'field',
            ],
            [
                'Field'			=> 'gid',
                'Type'			=> 'uint',
            ],
        ];
        $this->assertSame($expect, $describe);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testSetVariable(): void
    {
        $this->createHelper()->setVariable('AUTOCOMMIT', 0)->execute();
        $vars = Helper::pairsToAssoc($this->createHelper()->showVariables()->execute()->fetchAllAssoc());
        $this->assertEquals(0, $vars['autocommit']);

        $this->createHelper()->setVariable('AUTOCOMMIT', 1)->execute();
        $vars = Helper::pairsToAssoc($this->createHelper()->showVariables()->execute()->fetchAllAssoc());
        $this->assertEquals(1, $vars['autocommit']);

        $this->createHelper()->setVariable('@foo', 1, true);
        $this->createHelper()->setVariable('@foo', [0], true);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testCallSnippets(): void
    {
        $snippets = $this->createHelper()->callSnippets(
            'this is my document text',
            'rt',
            'is'
        )->execute()->fetchAllAssoc();
        $this->assertEquals([
            [
                'snippet' => 'this <b>is</b> my document text',
            ]
        ], $snippets);

        $snippets = $this->createHelper()->callSnippets(
            'this is my document text',
            'rt',
            'is',
            [
//				'query_mode'	=> 1,
                'before_match'	=> '<em>',
                'after_match'	=> '</em>',
            ]
        )->execute()->fetchAllAssoc();
        $this->assertEquals([
            [
                'snippet' => 'this <em>is</em> my document text',
            ]
        ], $snippets);

        $snippets = $this->createHelper()->callSnippets([
            'this is my document text',
            'another document',
        ], 'rt', 'is', [
            'allow_empty' => 1,
        ])->execute()->fetchAllAssoc();
        $this->assertEquals([
            [
                'snippet' => 'this <b>is</b> my document text',
            ],
            [
                'snippet' => '',
            ],
        ], $snippets);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testCallKeywords(): void
    {
        $keywords = $this->createHelper()->callKeywords(
            'test case',
            'rt'
        )->execute()->fetchAllAssoc();
        $this->assertEquals([
            [
                'qpos'       => '1',
                'tokenized'  => 'test',
                'normalized' => 'test',
            ],
            [
                'qpos'       => '2',
                'tokenized'  => 'case',
                'normalized' => 'case',
            ],
        ], $keywords);

        $keywords = $this->createHelper()->callKeywords(
            'test case',
            'rt',
            1
        )->execute()->fetchAllAssoc();
        $this->assertEquals([
            [
                'qpos'       => '1',
                'tokenized'  => 'test',
                'normalized' => 'test',
                'docs'       => '0',
                'hits'       => '0',
            ],
            [
                'qpos'       => '2',
                'tokenized'  => 'case',
                'normalized' => 'case',
                'docs'       => '0',
                'hits'       => '0',
            ],
        ], $keywords);
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function testUdfNotInstalled(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Sphinx expr: syntax error');

        self::$connection->query('SELECT MY_UDF()');
    }

    //	/**
    //	 * @throws ConnectionException
    //	 * @throws DatabaseException
    //	 * @throws SphinxQLException
    //	 */
    //	public function testCreateFunction(): void
    //	{
    //		$this->createHelper()->createFunction('my_udf', 'INT', 'test_udf.so')->execute();
//
    //		$this->assertSame([
    //			[
    //				'MY_UDF()' => '42',
    //			],
    //		],self::$connection->query('SELECT MY_UDF()')->fetchAllAssoc());
//
    //		$this->createHelper()->dropFunction('my_udf')->execute();
    //	}

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testTruncateRtIndex(): void
    {
        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->set([
                'id'		=> 1,
                'title'		=> 'this is a title',
                'content'	=> 'this is the content',
                'gid'		=> 100,
            ])
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(1, $result);

        $this->createHelper()->truncateRtIndex('rt')->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->fetchAllAssoc();

        $this->assertCount(0, $result);
    }

    /**
     * Actually executing these queries may not be useful nor easy to test
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function testMiscellaneous(): void
    {
        $query = $this->createHelper()->showMeta();
        $this->assertEquals('SHOW META', $query->compile()->getCompiled());

        $query = $this->createHelper()->showWarnings();
        $this->assertEquals('SHOW WARNINGS', $query->compile()->getCompiled());

        $query = $this->createHelper()->showStatus();
        $this->assertEquals('SHOW STATUS', $query->compile()->getCompiled());

        $query = $this->createHelper()->attachIndex('disk', 'rt');
        $this->assertEquals('ATTACH INDEX disk TO RTINDEX rt', $query->compile()->getCompiled());

        $query = $this->createHelper()->flushRtIndex('rt');
        $this->assertEquals('FLUSH RTINDEX rt', $query->compile()->getCompiled());

        $query = $this->createHelper()->optimizeIndex('rt');
        $this->assertEquals('OPTIMIZE INDEX rt', $query->compile()->getCompiled());

        $query = $this->createHelper()->showIndexStatus('rt');
        $this->assertEquals('SHOW INDEX rt STATUS', $query->compile()->getCompiled());

        $query = $this->createHelper()->flushRamchunk('rt');
        $this->assertEquals('FLUSH RAMCHUNK rt', $query->compile()->getCompiled());
    }
}
