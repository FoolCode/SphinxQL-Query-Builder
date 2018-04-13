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
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->adapter = new MultiResultSetAdapter($connection);
        $this->connection = $connection;
    }

}
