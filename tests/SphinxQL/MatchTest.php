<?php

use Foolz\SphinxQL\Match;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Tests\TestUtil;

class MatchTest extends \PHPUnit\Framework\TestCase
{
    public static $sphinxql = null;

    public static function setUpBeforeClass()
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        self::$sphinxql = new SphinxQL($conn);
    }

    /**
     * @return Match
     */
    protected function createMatch()
    {
        return new Match(self::$sphinxql);
    }

    public function testMatch()
    {
        $match = $this->createMatch()
            ->match('test');
        $this->assertEquals('test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test case');
        $this->assertEquals('(test case)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match(function ($m) {
                $m->match('a')->orMatch('b');
            });
        $this->assertEquals('(a | b)', $match->compile()->getCompiled());

        $sub = new Match(self::$sphinxql);
        $sub->match('a')->orMatch('b');
        $match = $this->createMatch()
            ->match($sub);
        $this->assertEquals('(a | b)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test|case');
        $this->assertEquals('test\|case', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match(SphinxQL::expr('test|case'));
        $this->assertEquals('test|case', $match->compile()->getCompiled());
    }

    public function testOrMatch()
    {
        $match = $this->createMatch()
            ->match('test')->orMatch();
        $this->assertEquals('test |', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')->orMatch('case');
        $this->assertEquals('test | case', $match->compile()->getCompiled());
    }

    public function testMaybe()
    {
        $match = $this->createMatch()
            ->match('test')
            ->maybe();
        $this->assertEquals('test MAYBE', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->maybe('case');
        $this->assertEquals('test MAYBE case', $match->compile()->getCompiled());
    }

    public function testNot()
    {
        $match = $this->createMatch()
            ->not()
            ->match('test');
        $this->assertEquals('-test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->not('test');
        $this->assertEquals('-test', $match->compile()->getCompiled());
    }

    public function testField()
    {
        $match = $this->createMatch()
            ->field('*')
            ->match('test');
        $this->assertEquals('@* test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->field('title')
            ->match('test');
        $this->assertEquals('@title test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->field('body', 50)
            ->match('test');
        $this->assertEquals('@body[50] test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->field('title', 'body')
            ->match('test');
        $this->assertEquals('@(title,body) test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->field(array('title', 'body'))
            ->match('test');
        $this->assertEquals('@(title,body) test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->field('@relaxed')
            ->field('nosuchfield')
            ->match('test');
        $this->assertEquals('@@relaxed @nosuchfield test', $match->compile()->getCompiled());
    }

    public function testIgnoreField()
    {
        $match = $this->createMatch()
            ->ignoreField('title')
            ->match('test');
        $this->assertEquals('@!title test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->ignoreField('title', 'body')
            ->match('test');
        $this->assertEquals('@!(title,body) test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->ignoreField(array('title', 'body'))
            ->match('test');
        $this->assertEquals('@!(title,body) test', $match->compile()->getCompiled());
    }

    public function testPhrase()
    {
        $match = $this->createMatch()
            ->phrase('test case');
        $this->assertEquals('"test case"', $match->compile()->getCompiled());
    }

    public function testOrPhrase()
    {
        $match = $this->createMatch()
            ->phrase('test case')->orPhrase('another case');
        $this->assertEquals('"test case" | "another case"', $match->compile()->getCompiled());
    }

    public function testProximity()
    {
        $match = $this->createMatch()
            ->proximity('test case', 5);
        $this->assertEquals('"test case"~5', $match->compile()->getCompiled());
    }

    public function testQuorum()
    {
        $match = $this->createMatch()
            ->quorum('this is a test case', 3);
        $this->assertEquals('"this is a test case"/3', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->quorum('this is a test case', 0.5);
        $this->assertEquals('"this is a test case"/0.5', $match->compile()->getCompiled());
    }

    public function testBefore()
    {
        $match = $this->createMatch()
            ->match('test')
            ->before();
        $this->assertEquals('test <<', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->before('case');
        $this->assertEquals('test << case', $match->compile()->getCompiled());
    }

    public function testExact()
    {
        $match = $this->createMatch()
            ->match('test')
            ->exact('cases');
        $this->assertEquals('test =cases', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->exact()
            ->phrase('specific cases');
        $this->assertEquals('test ="specific cases"', $match->compile()->getCompiled());
    }

    public function testBoost()
    {
        $match = $this->createMatch()
            ->match('test')
            ->boost(1.2);
        $this->assertEquals('test^1.2', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->boost('case', 1.2);
        $this->assertEquals('test case^1.2', $match->compile()->getCompiled());
    }

    public function testNear()
    {
        $match = $this->createMatch()
            ->match('test')
            ->near(3);
        $this->assertEquals('test NEAR/3', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->near('case', 3);
        $this->assertEquals('test NEAR/3 case', $match->compile()->getCompiled());
    }

    public function testSentence()
    {
        $match = $this->createMatch()
            ->match('test')
            ->sentence();
        $this->assertEquals('test SENTENCE', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->sentence('case');
        $this->assertEquals('test SENTENCE case', $match->compile()->getCompiled());
    }

    public function testParagraph()
    {
        $match = $this->createMatch()
            ->match('test')
            ->paragraph();
        $this->assertEquals('test PARAGRAPH', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')
            ->paragraph('case');
        $this->assertEquals('test PARAGRAPH case', $match->compile()->getCompiled());
    }

    public function testZone()
    {
        $match = $this->createMatch()
            ->zone('th');
        $this->assertEquals('ZONE:(th)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->zone(array('h3', 'h4'));
        $this->assertEquals('ZONE:(h3,h4)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->zone('th', 'test');
        $this->assertEquals('ZONE:(th) test', $match->compile()->getCompiled());
    }

    public function testZonespan()
    {
        $match = $this->createMatch()
            ->zonespan('th');
        $this->assertEquals('ZONESPAN:(th)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->zonespan('th', 'test');
        $this->assertEquals('ZONESPAN:(th) test', $match->compile()->getCompiled());
    }

    public function testCompile()
    {
        $match = $this->createMatch()
            ->phrase('hello world')
            ->field('title')
            ->proximity('example program', 5)
            ->field('body')
            ->match('python')
            ->not(function ($m) {
                $m->match('php')
                    ->orMatch('perl');
            })
            ->field('*')
            ->match('code');
        $this->assertEquals('"hello world" @title "example program"~5 @body python -(php | perl) @* code', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('bag of words')
            ->before()
            ->phrase('exact phrase')
            ->before('red')
            ->orMatch('green')
            ->orMatch('blue');
        $this->assertEquals('(bag of words) << "exact phrase" << red | green | blue', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('aaa')
            ->not(function ($m) {
                $m->match('bbb')
                    ->not('ccc ddd');
            });
        $this->assertEquals('aaa -(bbb -(ccc ddd))', $match->compile()->getCompiled());
    }

    // issue #82
    public function testClosureMisuse()
    {
        $match = $this->createMatch()
            ->match('strlen');
        $this->assertEquals('strlen', $match->compile()->getCompiled());
    }
}
