<?php
namespace Foolz\SphinxQL\Tests;

use Foolz\SphinxQL\Expression;

use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase{

	public function test__construct(){
		$this->assertNotNull(new Expression());
	}

}