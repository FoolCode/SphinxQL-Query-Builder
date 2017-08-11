<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

class MultiResultSetAdapter implements \Foolz\SphinxQL\Drivers\MultiResultSetAdapterInterface
{
    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @var Connection|null
     */
    protected $connection = null;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     */
    public function getNext()
    {
        if (
            !$this->valid() ||
            !$this->connection->getConnection()->more_results()
        ) {
            $this->valid = false;
        } else {
            $this->connection->getConnection()->next_result();
        }
    }

    /**
     * @return ResultSet
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     */
    public function current()
    {
        return ResultSet::make($this->connection, $this->connection->getConnection()->store_result());
    }

    /**
     * @return bool
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     */
    public function valid()
    {
        return $this->connection->getConnection()->errno == 0 && $this->valid;
    }
}
