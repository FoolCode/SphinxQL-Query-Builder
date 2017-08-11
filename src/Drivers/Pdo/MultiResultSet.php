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
     * @param PDOStatement|array|int $statement
     */
    public function __construct(MultiResultSetAdapter $adapter, $statement)
    {
        $this->adapter = $adapter;

        if ($statement instanceof PDOStatement) {
            $this->statement = $statement;
        } else {
            $this->stored = $statement; // for php < 5.4.0
        }
    }

    /**
     * @param PDOStatement|array|int $statement
     * @return MultiResultSet
     */
    public static function make($statement)
    {
        $adapter = new MultiResultSetAdapter($statement);
        return new MultiResultSet($adapter, $statement);
    }
}
