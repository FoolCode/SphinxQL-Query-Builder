<?php

use Foolz\SphinxQL\Expression as Expression;

class ExpressionTest extends PHPUnit_Framework_TestCase
{
    public function testValue()
    {
        $result = new Expression('');

        $this->assertInstanceOf('Foolz\Sphinxql\Expression', $result);
        $this->assertEquals('', (string) $result);

        $result = new Expression('* \\ Ç"" \'');

        $this->assertInstanceOf('Foolz\Sphinxql\Expression', $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }
}