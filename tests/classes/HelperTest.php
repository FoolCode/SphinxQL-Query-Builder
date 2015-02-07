<?php

use Foolz\SphinxQL\Drivers\Mysqli\Connection as Connection;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;

class HelperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Foolz\SphinxQL\Connection
     */
    public $conn;

    public function __construct()
    {
        $conn = new Connection();
        $conn->setParam('port', 9307);
        $this->conn = $conn;

        SphinxQL::create($this->conn)->query('TRUNCATE RTINDEX rt')->execute();
    }

    public function testShowTables()
    {
        $this->assertEquals(
            array(array('Index' => 'rt', 'Type' => 'rt')),
            Helper::create($this->conn)->showTables()->execute()->getStored()
        );
    }

    public function testDescribe()
    {
        $describe = Helper::create($this->conn)->describe('rt')->execute()->getStored();
        array_shift($describe);
        $this->assertSame(
            array(
                array('Field' => 'title', 'Type' => 'field'),
                array('Field' => 'content', 'Type' => 'field'),
                array('Field' => 'gid', 'Type' => 'uint'),
            ),
            $describe
        );
    }

    public function testSetVariable()
    {
        Helper::create($this->conn)->setVariable('AUTOCOMMIT', 0)->execute();
        $vars = Helper::pairsToAssoc(Helper::create($this->conn)->showVariables()->execute()->getStored());
        $this->assertEquals(0, $vars['autocommit']);

        Helper::create($this->conn)->setVariable('AUTOCOMMIT', 1)->execute();
        $vars = Helper::pairsToAssoc(Helper::create($this->conn)->showVariables()->execute()->getStored());
        $this->assertEquals(1, $vars['autocommit']);

        Helper::create($this->conn)->setVariable('@foo', 1, true);
        Helper::create($this->conn)->setVariable('@foo', array(0), true);
    }
}
