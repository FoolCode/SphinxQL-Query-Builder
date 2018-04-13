<?php

use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Tests\TestUtil;

/**
 * @author Vicent Valls
 */
class FacetTest  extends \PHPUnit\Framework\TestCase
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
    }

    /**
     * @return Facet
     */
    protected function createFacet()
    {
        return new Facet(self::$conn);
    }

    public function testFacet()
    {
        $facet = $this->createFacet()
            ->facet(array('gid'))
            ->getFacet();

        $this->assertEquals('FACET gid', $facet);

        $facet = $this->createFacet()
            ->facet(array('gid', 'title', 'content'))
            ->getFacet();

        $this->assertEquals('FACET gid, title, content', $facet);

        $facet = $this->createFacet()
            ->facet('gid', 'title', 'content')
            ->getFacet();

        $this->assertEquals('FACET gid, title, content', $facet);

        $facet = $this->createFacet()
            ->facet(array('aliAS' => 'gid'))
            ->getFacet();

        $this->assertEquals('FACET gid AS aliAS', $facet);

        $facet = $this->createFacet()
            ->facet(array('gid', 'name' => 'title', 'content'))
            ->getFacet();

        $this->assertEquals('FACET gid, title AS name, content', $facet);

        $facet = new Facet();
        $facet = $facet
            ->setConnection(self::$conn)
            ->facet('gid', array('name' => 'title'), 'content')
            ->getFacet();

        $this->assertEquals('FACET gid, title AS name, content', $facet);
    }

    public function testFacetFunction()
    {
        $facet = $this->createFacet()
            ->facetFunction('INTERVAL', array('price', 200, 400, 600, 800))
            ->getFacet();

        $this->assertEquals('FACET INTERVAL(price,200,400,600,800)', $facet);

        $facet = $this->createFacet()
            ->facetFunction('COUNT', 'gid')
            ->getFacet();

        $this->assertEquals('FACET COUNT(gid)', $facet);
    }

    public function testBy()
    {
        $facet = $this->createFacet()
            ->facet(array('gid', 'title', 'content'))
            ->by('gid')
            ->getFacet();

        $this->assertEquals('FACET gid, title, content BY gid', $facet);
    }

    public function testOrderBy()
    {
        $facet = $this->createFacet()
            ->facet(array('gid', 'title'))
            ->orderBy('gid', 'DESC')
            ->getFacet();

        $this->assertEquals('FACET gid, title ORDER BY gid DESC', $facet);

        $facet = $this->createFacet()
            ->facet(array('gid', 'content'))
            ->orderBy('gid', 'ASC')
            ->orderBy('content', 'DESC')
            ->getFacet();

        $this->assertEquals('FACET gid, content ORDER BY gid ASC, content DESC', $facet);
    }

    public function testOrderByFunction()
    {
        $facet = $this->createFacet()
            ->facet(array('gid', 'title'))
            ->orderByFunction('COUNT','*', 'DESC')
            ->getFacet();

        $this->assertEquals('FACET gid, title ORDER BY COUNT(*) DESC', $facet);
    }

    public function testLimit()
    {
        $facet = $this->createFacet()
            ->facet(array('gid', 'title'))
            ->orderByFunction('COUNT', '*', 'DESC')
            ->limit(5, 5)
            ->getFacet();

        $this->assertEquals('FACET gid, title ORDER BY COUNT(*) DESC LIMIT 5, 5', $facet);
    }
}
