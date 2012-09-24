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
				'title' => 'the story of a long test unit',
				'content' => 'once upon a time there was a foo in the bar',
				'gid' => 9001
			))
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->execute();
		
		$this->assertSame(array(
			array(
				'id' => '10',
				'weight' => '1',
				'gid' => '9001'
			)
		), $result);
		
		Sphinxql::insert()
			->into('rt')
			->columns('id', 'title', 'content', 'gid')
			->values(11, 'this is a title', 'this is the content', 100)
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->execute();
	
		$this->assertSame(array(
			array(
				'id' => '10',
				'weight' => '1',
				'gid' => '9001'
			),
			array(
				'id' => '11',
				'weight' => '1',
				'gid' => '100'
			),
		), $result);
		
		
		Sphinxql::insert()
			->into('rt')
			->value('id', 12)
			->value('title', 'simple logic')
			->value('content', 'inside the box there was the content')
			->value('gid', 200)
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->execute();
		
		$this->assertSame(array(
			array(
				'id' => '10',
				'weight' => '1',
				'gid' => '9001'
			),
			array(
				'id' => '11',
				'weight' => '1',
				'gid' => '100'
			),
			array(
				'id' => '12',
				'weight' => '1',
				'gid' => '200'
			),
		), $result);
		
		Sphinxql::insert()
			->into('rt')
			->columns('id', 'title', 'content', 'gid')
			->values(13, 'i am getting bored', 'with all this CONTENT', 300)
			->values(14, 'i want a vacation', 'the code is going to break sometime', 300)
			->values(15, 'there\'s no hope in this class', 'just give up', 300)
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->execute();
		
		$this->assertCount(6, $result);
	}
	
	
	public function testReplace()
	{
		Sphinxql::replace()
			->into('rt')
			->set(array(
				'id' => 10,
				'title' => 'modified',
				'content' => 'this field was modified with replace',
				'gid' => 9002
			))
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->where('id', '=', 10)
			->execute();
		
		$this->assertSame('9002', $result[0]['gid']);
		
		Sphinxql::replace()
			->into('rt')
			->columns('id', 'title', 'content', 'gid')
			->values(10, 'modifying the same line again', 'because i am that lazy', 9003)
			->values(11, 'i am getting really creative with these strings', 'i\'ll need them to test MATCH!', 300)
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->where('id', 'IN', array(10, 11))
			->execute();
		
		$this->assertSame('9003', $result[0]['gid']);
		$this->assertSame('300', $result[1]['gid']);
		
		Sphinxql::replace()
			->into('rt')
			->value('id', 11)
			->value('title', 'replacing value by value')
			->value('content', 'i have no idea who would use this directly')
			->value('gid', 200)
			->execute();
		
		$result = Sphinxql::select()
			->from('rt')
			->where('id', '=', 11)
			->execute();
		
		$this->assertSame('200', $result[0]['gid']);
	}
	
	
}