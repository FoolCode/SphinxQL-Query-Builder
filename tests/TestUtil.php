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
        $connection = '\\Foolz\\SphinxQL\\Drivers\\'.self::getDriver().'\\Connection';

        return new $connection();
    }

    public static function getDriver(): string
    {
        return $GLOBALS['driver'];
        //		return $GLOBALS['_SERVER']['DRIVER'] ?? '';
    }

    public static function getSearchBuild(): string
    {
        return $GLOBALS['_SERVER']['SEARCH_BUILD'] ?? '';
    }
}
