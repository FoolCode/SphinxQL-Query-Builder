<?php

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Tests\TestUtil;

class HelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ConnectionInterface
     */
    public $conn;

    public function setUp()
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        $this->conn = $conn;

        $this->createSphinxQL()->query('TRUNCATE RTINDEX rt')->execute();
    }

    /**
     * @return SphinxQL
     */
    protected function createSphinxQL()
    {
        return new SphinxQL($this->conn);
    }

    /**
     * @return Helper
     */
    protected function createHelper()
    {
        return new Helper($this->conn);
    }

    public function testShowTables()
    {
        $this->assertEquals(
            array(array('Index' => 'rt', 'Type' => 'rt')),
            $this->createHelper()->showTables('rt')->execute()->getStored()
        );
    }

    public function testDescribe()
    {
        $describe = $this->createHelper()->describe('rt')->execute()->getStored();
        array_shift($describe);
        $this->assertSame(
            array(
                array('Field' => 'title', 'Type' => 'field'),
                array('Field' => 'content', 'Type' => 'field'),
                array('Field' => 'gid', 'Type' => 'uint'),
            ),
            $describe
        );
    }

    public function testSetVariable()
    {
        $this->createHelper()->setVariable('AUTOCOMMIT', 0)->execute();
        $vars = Helper::pairsToAssoc($this->createHelper()->showVariables()->execute()->getStored());
        $this->assertEquals(0, $vars['autocommit']);

        $this->createHelper()->setVariable('AUTOCOMMIT', 1)->execute();
        $vars = Helper::pairsToAssoc($this->createHelper()->showVariables()->execute()->getStored());
        $this->assertEquals(1, $vars['autocommit']);

        $this->createHelper()->setVariable('@foo', 1, true);
        $this->createHelper()->setVariable('@foo', array(0), true);
    }

    public function testCallSnippets()
    {
        $snippets = $this->createHelper()->callSnippets(
            'this is my document text',
            'rt',
            'is'
        )->execute()->getStored();
        $this->assertEquals(
            array(array('snippet' => 'this <b>is</b> my document text')),
            $snippets
        );

        $snippets = $this->createHelper()->callSnippets(
            'this is my document text',
            'rt',
            'is',
            array(
                'query_mode'   => 1,
                'before_match' => '<em>',
                'after_match'  => '</em>',
            )
        )->execute()->getStored();
        $this->assertEquals(
            array(array('snippet' => 'this <em>is</em> my document text')),
            $snippets
        );

        $snippets = $this->createHelper()->callSnippets(
            array('this is my document text', 'another document'),
            'rt',
            'is',
            array('allow_empty' => 1)
        )->execute()->getStored();
        $this->assertEquals(
            array(
                array('snippet' => 'this <b>is</b> my document text'),
                array('snippet' => ''),
            ),
            $snippets
        );
    }

    public function testCallKeywords()
    {
        $keywords = $this->createHelper()->callKeywords(
            'test case',
            'rt'
        )->execute()->getStored();
        $this->assertEquals(
            array(
                array(
                    'qpos'       => '1',
                    'tokenized'  => 'test',
                    'normalized' => 'test',
                ),
                array(
                    'qpos'       => '2',
                    'tokenized'  => 'case',
                    'normalized' => 'case',
                ),
            ),
            $keywords
        );

        $keywords = $this->createHelper()->callKeywords(
            'test case',
            'rt',
            1
        )->execute()->getStored();
        $this->assertEquals(
            array(
                array(
                    'qpos'       => '1',
                    'tokenized'  => 'test',
                    'normalized' => 'test',
                    'docs'       => '0',
                    'hits'       => '0',
                ),
                array(
                    'qpos'       => '2',
                    'tokenized'  => 'case',
                    'normalized' => 'case',
                    'docs'       => '0',
                    'hits'       => '0',
                ),
            ),
            $keywords
        );
    }

    /**
     * @expectedException        Foolz\SphinxQL\Exception\DatabaseException
     * @expectedExceptionMessage Sphinx expr: syntax error
     */
    public function testUdfNotInstalled()
    {
        $this->conn->query('SELECT MY_UDF()');
    }

    public function testCreateFunction()
    {
        $this->createHelper()->createFunction('my_udf', 'INT', 'test_udf.so')->execute();
        $this->assertSame(
            array(array('MY_UDF()' => '42')),
            $this->conn->query('SELECT MY_UDF()')->getStored()
        );
        $this->createHelper()->dropFunction('my_udf')->execute();
    }

    /**
     * @covers \Foolz\SphinxQL\Helper::truncateRtIndex
     */
    public function testTruncateRtIndex()
    {
        $this->createSphinxQL()
            ->insert()
            ->into('rt')
            ->set(array(
                'id' => 1,
                'title' => 'this is a title',
                'content' => 'this is the content',
                'gid' => 100
            ))
            ->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(1, $result);

        $this->createHelper()->truncateRtIndex('rt')->execute();

        $result = $this->createSphinxQL()
            ->select()
            ->from('rt')
            ->execute()
            ->getStored();

        $this->assertCount(0, $result);
    }

    // actually executing these queries may not be useful nor easy to test
    public function testMiscellaneous()
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
