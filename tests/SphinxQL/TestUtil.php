<?php
namespace Foolz\SphinxQL\Tests;

use Foolz\SphinxQL\Drivers\ConnectionBase;

class TestUtil
{
    /**
     * @return ConnectionBase
     */
    public static function getConnectionDriver(): ConnectionBase
    {
        $connection = '\\Foolz\\SphinxQL\\Drivers\\'.$GLOBALS['driver'].'\\Connection';

        return new $connection();
    }
}
