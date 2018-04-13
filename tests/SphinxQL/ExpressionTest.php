<?php

use Foolz\SphinxQL\Expression as Expression;

class ExpressionTest extends \PHPUnit\Framework\TestCase
{
    public function testValue()
    {
        $result = new Expression('');

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('', (string) $result);

        $result = new Expression('* \\ Ç"" \'');

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }
}
