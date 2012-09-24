<?php

use Foolz\Sphinxql\Sphinxql;
use Foolz\Sphinxql\SphinxqlConnection;
use Foolz\Sphinxql\SphinxqlExpression;

class SphinxqlTest extends PHPUnit_Framework_TestCase
{
	
	public function testExpr()
	{
		$result = Sphinxql::expr('');
			
		$this->assertInstanceOf('Foolz\Sphinxql\SphinxqlExpression', $result);
		$this->assertEquals('', (string) $result);
		
		$result = Sphinxql::expr('* \\ Ç"" \'');
			
		$this->assertInstanceOf('Foolz\Sphinxql\SphinxqlExpression', $result);
		$this->assertEquals('* \\ Ç"" \'', (string) $result);
	}
	
	
}