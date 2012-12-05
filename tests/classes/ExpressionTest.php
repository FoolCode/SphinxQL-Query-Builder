<?php

use Foolz\Sphinxql\Expression as SphinxqlExpression;

class ExpressionTest extends PHPUnit_Framework_TestCase
{

	public function testValue()
	{
		$result = new SphinxqlExpression('');

		$this->assertInstanceOf('Foolz\Sphinxql\Expression', $result);
		$this->assertEquals('', (string) $result);

		$result = new SphinxqlExpression('* \\ Ç"" \'');

		$this->assertInstanceOf('Foolz\Sphinxql\Expression', $result);
		$this->assertEquals('* \\ Ç"" \'', (string) $result);
	}

}