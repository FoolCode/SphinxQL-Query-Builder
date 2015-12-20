<?php

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Match;
use Foolz\SphinxQL\Tests\TestUtil;

class MatchTest extends PHPUnit_Framework_TestCase
{
    public static $sphinxql = null;

    public static function setUpBeforeClass()
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        self::$sphinxql = SphinxQL::create($conn);
    }

    public function testMatch()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test');
        $this->assertEquals('test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test case');
        $this->assertEquals('(test case)', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match(function ($m) {
                $m->match('a')->orMatch('b');
            });
        $this->assertEquals('(a | b)', $match->compile()->getCompiled());

        $sub = new Match(self::$sphinxql);
        $sub->match('a')->orMatch('b');
        $match = Match::create(self::$sphinxql)
            ->match($sub);
        $this->assertEquals('(a | b)', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test|case');
        $this->assertEquals('test\|case', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match(SphinxQL::expr('test|case'));
        $this->assertEquals('test|case', $match->compile()->getCompiled());
    }

    public function testOrMatch()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')->orMatch();
        $this->assertEquals('test |', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')->orMatch('case');
        $this->assertEquals('test | case', $match->compile()->getCompiled());
    }

    public function testMaybe()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->maybe();
        $this->assertEquals('test MAYBE', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->maybe('case');
        $this->assertEquals('test MAYBE case', $match->compile()->getCompiled());
    }

    public function testNot()
    {
        $match = Match::create(self::$sphinxql)
            ->not()
            ->match('test');
        $this->assertEquals('-test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->not('test');
        $this->assertEquals('-test', $match->compile()->getCompiled());
    }

    public function testField()
    {
        $match = Match::create(self::$sphinxql)
            ->field('*')
            ->match('test');
        $this->assertEquals('@* test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->field('title')
            ->match('test');
        $this->assertEquals('@title test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->field('body', 50)
            ->match('test');
        $this->assertEquals('@body[50] test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->field('title', 'body')
            ->match('test');
        $this->assertEquals('@(title,body) test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->field(array('title', 'body'))
            ->match('test');
        $this->assertEquals('@(title,body) test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->field('@relaxed')
            ->field('nosuchfield')
            ->match('test');
        $this->assertEquals('@@relaxed @nosuchfield test', $match->compile()->getCompiled());
    }

    public function testIgnoreField()
    {
        $match = Match::create(self::$sphinxql)
            ->ignoreField('title')
            ->match('test');
        $this->assertEquals('@!title test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->ignoreField('title', 'body')
            ->match('test');
        $this->assertEquals('@!(title,body) test', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->ignoreField(array('title', 'body'))
            ->match('test');
        $this->assertEquals('@!(title,body) test', $match->compile()->getCompiled());
    }

    public function testPhrase()
    {
        $match = Match::create(self::$sphinxql)
            ->phrase('test case');
        $this->assertEquals('"test case"', $match->compile()->getCompiled());
    }

    public function testProximity()
    {
        $match = Match::create(self::$sphinxql)
            ->proximity('test case', 5);
        $this->assertEquals('"test case"~5', $match->compile()->getCompiled());
    }

    public function testQuorum()
    {
        $match = Match::create(self::$sphinxql)
            ->quorum('this is a test case', 3);
        $this->assertEquals('"this is a test case"/3', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->quorum('this is a test case', 0.5);
        $this->assertEquals('"this is a test case"/0.5', $match->compile()->getCompiled());
    }

    public function testBefore()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->before();
        $this->assertEquals('test <<', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->before('case');
        $this->assertEquals('test << case', $match->compile()->getCompiled());
    }

    public function testExact()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->exact('cases');
        $this->assertEquals('test =cases', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->exact()
            ->phrase('specific cases');
        $this->assertEquals('test ="specific cases"', $match->compile()->getCompiled());
    }

    public function testBoost()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->boost(1.2);
        $this->assertEquals('test^1.2', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->boost('case', 1.2);
        $this->assertEquals('test case^1.2', $match->compile()->getCompiled());
    }

    public function testNear()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->near(3);
        $this->assertEquals('test NEAR/3', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->near('case', 3);
        $this->assertEquals('test NEAR/3 case', $match->compile()->getCompiled());
    }

    public function testSentence()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->sentence();
        $this->assertEquals('test SENTENCE', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->sentence('case');
        $this->assertEquals('test SENTENCE case', $match->compile()->getCompiled());
    }

    public function testParagraph()
    {
        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->paragraph();
        $this->assertEquals('test PARAGRAPH', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->match('test')
            ->paragraph('case');
        $this->assertEquals('test PARAGRAPH case', $match->compile()->getCompiled());
    }

    public function testZone()
    {
        $match = Match::create(self::$sphinxql)
            ->zone('th');
        $this->assertEquals('ZONE:(th)', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->zone(array('h3', 'h4'));
        $this->assertEquals('ZONE:(h3,h4)', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->zone('th', 'test');
        $this->assertEquals('ZONE:(th) test', $match->compile()->getCompiled());
    }

    public function testZonespan()
    {
        $match = Match::create(self::$sphinxql)
            ->zonespan('th');
        $this->assertEquals('ZONESPAN:(th)', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
            ->zonespan('th', 'test');
        $this->assertEquals('ZONESPAN:(th) test', $match->compile()->getCompiled());
    }

    public function testCompile()
    {
        $match = Match::create(self::$sphinxql)
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

        $match = Match::create(self::$sphinxql)
            ->match('bag of words')
            ->before()
            ->phrase('exact phrase')
            ->before('red')
            ->orMatch('green')
            ->orMatch('blue');
        $this->assertEquals('(bag of words) << "exact phrase" << red | green | blue', $match->compile()->getCompiled());

        $match = Match::create(self::$sphinxql)
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
        $match = Match::create(self::$sphinxql)
            ->match('strlen');
        $this->assertEquals('strlen', $match->compile()->getCompiled());
    }
}
