<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\ResultSetBase;
use PDOStatement;

class ResultSet extends ResultSetBase
{

    protected $statement = null;

    /**
     * @param ResultSetAdapter $adapter
     * @param PDOStatement $statement
     */
    public function __construct(ResultSetAdapter $adapter, PDOStatement $statement)
    {
        $this->statement = $statement;
        $this->adapter = $adapter;
        $this->init();
        $this->store();
    }

    /**
     * @param PDOStatement $statement
     * @return ResultSet
     */
    public static function make(PDOStatement $statement)
    {
        $adapter = new ResultSetAdapter($statement);
        return new ResultSet($adapter, $statement);
    }

    /**
     * Get the result object returned by PHP's MySQLi
     *
     * @return \Pdostatement
     *
     * @codeCoverageIgnore
     */
    public function getResultObject()
    {
        return $this->statement;
    }
}
