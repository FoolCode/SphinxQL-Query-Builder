<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\SphinxqlConnection;
use Foolz\Sphinxql\SphinxqlExpression;

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
			->where('id', 'IN', array(10, 11, 12, 13, 14, 15))
			->execute();
	}
	
	public function testExpr()
	{
		$result = Sphinxql::expr('');
			
		$this->assertInstanceOf('Foolz\Sphinxql\SphinxqlExpression', $result);
		$this->assertEquals('', (string) $result);
		
		$result = Sphinxql::expr('* \\ Ç"" \'');
			
		$this->assertInstanceOf('Foolz\Sphinxql\SphinxqlExpression', $result);
		$this->assertEquals('* \\ Ç"" \'', (string) $result);
	}
	
	
	public function testSetVariable()
	{
		Sphinxql::setVariable('AUTOCOMMIT', 1);
		$vars = Sphinxql::variables();
		$this->assertEquals(1, $vars['autocommit']);
		
		Sphinxql::setVariable('AUTOCOMMIT', 0);
		$vars = Sphinxql::variables();
		$this->assertEquals(0, $vars['autocommit']);
		
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
		$this->assertSame(
			array(
				array('Field' => 'id', 'Type' => 'integer'),
				array('Field' => 'title', 'Type' => 'field'),
				array('Field' => 'content', 'Type' => 'field'),
				array('Field' => 'gid', 'Type' => 'uint'),
			),
			Sphinxql::describe('rt')
		);
	}
	
	public function testInsert()
	{
		Sphinxql::insert()
			->into('rt')
			->set(array(
				'id' => 10,
				'title' => 'foo',
				'content' => 'bar',
				'gid' => 9001
			))
			->execute();
	}
}