<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;


use Foolz\SphinxQL\Drivers\MultiResultSetBase;

class MultiResultSet extends MultiResultSetBase
{
    /**
     * @var Connection
     */
    public $connection;

    /**
     * @param MultiResultSetAdapter $adapter
     * @param Connection $connection
     */
    public function __construct(MultiResultSetAdapter $adapter, Connection $connection)
    {
        $this->adapter = $adapter;
        $this->connection = $connection;
    }

    /**
     * @param Connection $connection
     * @return MultiResultSet
     */
    public static function make(Connection $connection)
    {
        $adapter = new MultiResultSetAdapter($connection);
        return new MultiResultSet($adapter, $connection);
    }
}
