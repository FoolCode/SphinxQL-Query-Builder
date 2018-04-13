<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\ResultSetBase;
use PDOStatement;

class ResultSet extends ResultSetBase
{

    protected $statement;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
        $this->adapter = new ResultSetAdapter($statement);
        $this->init();
        $this->store();
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
