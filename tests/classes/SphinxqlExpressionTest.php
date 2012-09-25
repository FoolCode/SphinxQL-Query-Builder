<?php

use Foolz\Sphinxql\SphinxqlExpression;

class SphinxqlExpressionTest extends PHPUnit_Framework_TestCase
{
	
	public function testValue()
	{
		$result = new SphinxqlExpression('');
			
		$this->assertInstanceOf('Foolz\Sphinxql\SphinxqlExpression', $result);
		$this->assertEquals('', (string) $result);
		
		$result = new SphinxqlExpression('* \\ Ç"" \'');
			
		$this->assertInstanceOf('Foolz\Sphinxql\SphinxqlExpression', $result);
		$this->assertEquals('* \\ Ç"" \'', (string) $result);
	}
	
}