<?php

namespace Foolz\SphinxQL\Drivers\Pdo;


use Foolz\SphinxQL\Drivers\MultiResultSetBase;
use PDOStatement;

class MultiResultSet extends MultiResultSetBase
{
    /**
     * @var PDOStatement
     */
    public $statement;

    /**
     * @param MultiResultSetAdapter $adapter
     * @param PDOStatement $statement
     */
    public function __construct(MultiResultSetAdapter $adapter, PDOStatement $statement)
    {
        $this->adapter = $adapter;
        $this->statement = $statement;
    }

    /**
     * @param PDOStatement $statement
     * @return MultiResultSet
     */
    public static function make(PDOStatement $statement)
    {
        $adapter = new MultiResultSetAdapter($statement);
        return new MultiResultSet($adapter, $statement);
    }
}
