<?php

namespace Foolz\SphinxQL\Tests;

use Foolz\SphinxQL\Drivers\Mysqli\Connection as MysqliConnection;
use Foolz\SphinxQL\Drivers\Pdo\Connection as PdoConnection;

$GLOBALS['driver'] = 'Mysqli';

class TestUtil
{
    /**
     * @return PdoConnection|MysqliConnection
     */
    public static function getConnectionDriver()
    {
        $connection = '\\Foolz\\SphinxQL\\Drivers\\'.$GLOBALS['driver'].'\\Connection';

        return new $connection();
    }
}
