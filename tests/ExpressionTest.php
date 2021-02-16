<?php
namespace Foolz\SphinxQL\Tests;

use Foolz\SphinxQL\Expression;

use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    public function testValue(): void
    {
        $result = new Expression();

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('', (string) $result);

        $result = new Expression('* \\ Ç"" \'');

        $this->assertInstanceOf(Expression::class, $result);
        $this->assertEquals('* \\ Ç"" \'', (string) $result);
    }
}
