<?php

namespace Foolz\SphinxQL\Drivers\Pdo;


use Foolz\SphinxQL\Drivers\MultiResultSetBase;
use PDOStatement;

class MultiResultSet extends MultiResultSetBase
{
    /**
     * @var PDOStatement
     */
    protected $statement;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->adapter = new MultiResultSetAdapter($statement);
        $this->statement = $statement;
    }

}
