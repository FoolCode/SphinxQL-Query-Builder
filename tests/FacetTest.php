<?php
namespace Foolz\SphinxQL\Tests;

use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Facet;

use PHPUnit\Framework\TestCase;

class FacetTest extends TestCase{

//	public static $DATA = [
//		0 => [
//			'id'		=> '10',
//			'gid'		=> '9003',
//			'title'		=> 'modifying the same line again',
//			'content'	=> 'because i am that lazy',
//		],
//		1 => [
//			'id'		=> '11',
//			'gid'		=> '201',
//			'title'		=> 'replacing value by value',
//			'content'	=> 'i have no idea who would use this directly',
//		],
//		2 => [
//			'id'		=> '12',
//			'gid'		=> '200',
//			'title'		=> 'simple logic',
//			'content'	=> 'inside the box there was the content',
//		],
//		3 => [
//			'id'		=> '13',
//			'gid'		=> '304',
//			'title'		=> 'i am getting bored',
//			'content'	=> 'with all this CONTENT',
//		],
//		4 => [
//			'id'		=> '14',
//			'gid'		=> '304',
//			'title'		=> 'i want a vacation',
//			'content'	=> 'the code is going to break sometime',
//		],
//		5 => [
//			'id'		=> '15',
//			'gid'		=> '304',
//			'title'		=> 'there\'s no hope in this class',
//			'content'	=> 'just give up',
//		],
//		6 => [
//			'id'		=> '16',
//			'gid'		=> '500',
//			'title'		=> 'we need to test',
//			'content'	=> 'selecting the best result in groups',
//		],
//		7 => [
//			'id'		=> '17',
//			'gid'		=> '500',
//			'title'		=> 'what is there to do',
//			'content'	=> 'we need to create dummy data for tests',
//		],
//	];

	/**
	 * @return Facet
	 */
	protected function createFacet(): Facet
	{
		return new Facet(null);
	}

	/**
	 * @throws SphinxQLException
	 */
	public function testFacet(): void{
		$facet = $this->createFacet()
			->facet(['gid'])
			->getFacet();

		$this->assertEquals('FACET gid', $facet);

		$facet = $this->createFacet()
			->facet(['gid', 'title', 'content'])
			->getFacet();

		$this->assertEquals('FACET gid, title, content', $facet);

		$facet = $this->createFacet()
			->facet('gid', 'title', 'content')
			->getFacet();

		$this->assertEquals('FACET gid, title, content', $facet);

		$facet = $this->createFacet()
			->facet(['aliAS' => 'gid'])
			->getFacet();

		$this->assertEquals('FACET gid AS aliAS', $facet);

		$facet = $this->createFacet()
			->facet(['gid', 'name' => 'title', 'content'])
			->getFacet();

		$this->assertEquals('FACET gid, title AS name, content', $facet);

		$facet = new Facet();
		$facet = $facet
			->facet('gid', ['name' => 'title'], 'content')
			->getFacet();

		$this->assertEquals('FACET gid, title AS name, content', $facet);
	}

	/**
	 * @throws SphinxQLException
	 */
	public function testFacetFunction(): void
	{
		$facet = $this->createFacet()
			->facetFunction('INTERVAL',['price', 200, 400, 600, 800])
			->getFacet();

		$this->assertEquals('FACET INTERVAL(price,200,400,600,800)', $facet);

		$facet = $this->createFacet()
			->facetFunction('COUNT', 'gid')
			->getFacet();

		$this->assertEquals('FACET COUNT(gid)', $facet);
	}

	/**
	 * @throws SphinxQLException
	 */
	public function testBy(): void
	{
		$facet = $this->createFacet()
			->facet(['gid', 'title', 'content'])
			->by('gid')
			->getFacet();

		$this->assertEquals('FACET gid, title, content BY gid', $facet);
	}

	/**
	 * @throws SphinxQLException
	 */
	public function testOrderBy(): void
	{
		$facet = $this->createFacet()
			->facet(['gid', 'title'])
			->orderBy('gid', 'DESC')
			->getFacet();

		$this->assertEquals('FACET gid, title ORDER BY gid DESC', $facet);

		$facet = $this->createFacet()
			->facet(['gid', 'content'])
			->orderBy('gid', 'ASC')
			->orderBy('content', 'DESC')
			->getFacet();

		$this->assertEquals('FACET gid, content ORDER BY gid ASC, content DESC', $facet);
	}

	/**
	 * @throws SphinxQLException
	 */
	public function testOrderByFunction(): void
	{
		$facet = $this->createFacet()
			->facet(['gid', 'title'])
			->orderByFunction('COUNT', '*', 'DESC')
			->getFacet();

		$this->assertEquals('FACET gid, title ORDER BY COUNT(*) DESC', $facet);
	}

	/**
	 * @throws SphinxQLException
	 */
	public function testLimit(): void
	{
		$facet = $this->createFacet()
			->facet(['gid', 'title'])
			->orderByFunction('COUNT', '*', 'DESC')
			->limit(5, 5)
			->getFacet();

		$this->assertEquals('FACET gid, title ORDER BY COUNT(*) DESC LIMIT 5, 5', $facet);
	}

}