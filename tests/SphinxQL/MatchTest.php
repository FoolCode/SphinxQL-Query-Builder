<?php
use Foolz\SphinxQL\MatchBuilder;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Tests\TestUtil;

use PHPUnit\Framework\TestCase;

class MatchTest extends TestCase
{
    public static $sphinxql;

    public static function setUpBeforeClass(): void
    {
        $conn = TestUtil::getConnectionDriver();
        $conn->setParam('port', 9307);
        self::$sphinxql = new SphinxQL($conn);
    }

    /**
     * @return MatchBuilder
     */
    protected function createMatch(): MatchBuilder
    {
        return new MatchBuilder(self::$sphinxql);
    }

    public function testMatch(): void
    {
        $match = $this->createMatch()
            ->match('test');
        $this->assertEquals('test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test case');
        $this->assertEquals('(test case)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match(static function (MatchBuilder $m) {
                $m->match('a')->orMatch('b');
            });
        $this->assertEquals('(a | b)', $match->compile()->getCompiled());

        $sub = new MatchBuilder(self::$sphinxql);
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

    public function testOrMatch(): void
    {
        $match = $this->createMatch()
            ->match('test')->orMatch();
        $this->assertEquals('test |', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->match('test')->orMatch('case');
        $this->assertEquals('test | case', $match->compile()->getCompiled());
    }

    public function testMaybe(): void
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

    public function testNot(): void
    {
        $match = $this->createMatch()
            ->not()
            ->match('test');
        $this->assertEquals('-test', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->not('test');
        $this->assertEquals('-test', $match->compile()->getCompiled());
    }

    public function testField(): void
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

    public function testIgnoreField(): void
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

    public function testPhrase(): void
    {
        $match = $this->createMatch()
            ->phrase('test case');
        $this->assertEquals('"test case"', $match->compile()->getCompiled());
    }

    public function testOrPhrase(): void
    {
        $match = $this->createMatch()
            ->phrase('test case')->orPhrase('another case');
        $this->assertEquals('"test case" | "another case"', $match->compile()->getCompiled());
    }

    public function testProximity(): void
    {
        $match = $this->createMatch()
            ->proximity('test case', 5);
        $this->assertEquals('"test case"~5', $match->compile()->getCompiled());
    }

    public function testQuorum(): void
    {
        $match = $this->createMatch()
            ->quorum('this is a test case', 3);
        $this->assertEquals('"this is a test case"/3', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->quorum('this is a test case', 0.5);
        $this->assertEquals('"this is a test case"/0.5', $match->compile()->getCompiled());
    }

    public function testBefore(): void
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

    public function testExact(): void
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

    public function testBoost(): void
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

    public function testNear(): void
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

    public function testSentence(): void
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

    public function testParagraph(): void
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

    public function testZone(): void
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

    public function testZonespan(): void
    {
        $match = $this->createMatch()
            ->zonespan('th');
        $this->assertEquals('ZONESPAN:(th)', $match->compile()->getCompiled());

        $match = $this->createMatch()
            ->zonespan('th', 'test');
        $this->assertEquals('ZONESPAN:(th) test', $match->compile()->getCompiled());
    }

    public function testCompile(): void
    {
        $match = $this->createMatch()
            ->phrase('hello world')
            ->field('title')
            ->proximity('example program', 5)
            ->field('body')
            ->match('python')
            ->not(static function (MatchBuilder $m) {
                $m->match('php')->orMatch('perl');
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
            ->not(static function (MatchBuilder $m) {
                $m->match('bbb')->not('ccc ddd');
            });
        $this->assertEquals('aaa -(bbb -(ccc ddd))', $match->compile()->getCompiled());
    }

    /**
     * Issue #82
     */
    public function testClosureMisuse(): void
    {
        $match = $this->createMatch()
            ->match('strlen');
        $this->assertEquals('strlen', $match->compile()->getCompiled());
    }
}
