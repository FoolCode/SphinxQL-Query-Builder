<?php

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\Tests\TestUtil;

class HelperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ConnectionInterface
     */
    public $conn;

    public function __construct()
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        $this->conn = $conn;

        SphinxQL::create($this->conn)->query('TRUNCATE RTINDEX rt')->execute();
    }

    public function testShowTables()
    {
        $this->assertEquals(
            array(array('Index' => 'rt', 'Type' => 'rt')),
            Helper::create($this->conn)->showTables()->execute()->getStored()
        );
    }

    public function testDescribe()
    {
        $describe = Helper::create($this->conn)->describe('rt')->execute()->getStored();
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
        Helper::create($this->conn)->setVariable('AUTOCOMMIT', 0)->execute();
        $vars = Helper::pairsToAssoc(Helper::create($this->conn)->showVariables()->execute()->getStored());
        $this->assertEquals(0, $vars['autocommit']);

        Helper::create($this->conn)->setVariable('AUTOCOMMIT', 1)->execute();
        $vars = Helper::pairsToAssoc(Helper::create($this->conn)->showVariables()->execute()->getStored());
        $this->assertEquals(1, $vars['autocommit']);

        Helper::create($this->conn)->setVariable('@foo', 1, true);
        Helper::create($this->conn)->setVariable('@foo', array(0), true);
    }

    public function testCallSnippets()
    {
        $snippets = Helper::create($this->conn)->callSnippets(
            'this is my document text',
            'rt',
            'is'
        )->execute()->getStored();
        $this->assertEquals(
            array(array('snippet' => 'this <b>is</b> my document text')),
            $snippets
        );

        $snippets = Helper::create($this->conn)->callSnippets(
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

        $snippets = Helper::create($this->conn)->callSnippets(
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
        $keywords = Helper::create($this->conn)->callKeywords(
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

        $keywords = Helper::create($this->conn)->callKeywords(
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
        Helper::create($this->conn)->createFunction('my_udf', 'INT', 'test_udf.so')->execute();
        $this->assertSame(
            array(array('MY_UDF()' => '42')),
            $this->conn->query('SELECT MY_UDF()')->getStored()
        );
        Helper::create($this->conn)->dropFunction('my_udf')->execute();
    }

    // actually executing these queries may not be useful nor easy to test
    public function testMiscellaneous()
    {
        $query = Helper::create($this->conn)->showMeta();
        $this->assertEquals('SHOW META', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->showWarnings();
        $this->assertEquals('SHOW WARNINGS', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->showStatus();
        $this->assertEquals('SHOW STATUS', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->attachIndex('disk', 'rt');
        $this->assertEquals('ATTACH INDEX disk TO RTINDEX rt', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->flushRtIndex('rt');
        $this->assertEquals('FLUSH RTINDEX rt', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->optimizeIndex('rt');
        $this->assertEquals('OPTIMIZE INDEX rt', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->showIndexStatus('rt');
        $this->assertEquals('SHOW INDEX rt STATUS', $query->compile()->getCompiled());

        $query = Helper::create($this->conn)->flushRamchunk('rt');
        $this->assertEquals('FLUSH RAMCHUNK rt', $query->compile()->getCompiled());
    }
}
