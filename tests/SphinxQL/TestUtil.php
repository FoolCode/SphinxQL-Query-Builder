<?php

namespace Foolz\SphinxQL\Tests;

class TestUtil
{
    public static function getConnectionDriver()
    {
        $connection = '\\Foolz\\SphinxQL\\Drivers\\'.$GLOBALS['driver'].'\\Connection';

        return new $connection();
    }
}
