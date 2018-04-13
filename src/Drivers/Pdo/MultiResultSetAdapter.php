<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\MultiResultSetAdapterInterface;

class MultiResultSetAdapter implements MultiResultSetAdapterInterface
{
    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @var \PDOStatement
     */
    protected $statement;

    public function __construct($statement)
    {
        $this->statement = $statement;
    }

    public function getNext()
    {
        if (
            !$this->valid() ||
            !$this->statement->nextRowset()
        ) {
            $this->valid = false;
        }
    }

    /**
     * @return ResultSet
     */
    public function current()
    {
        return new ResultSet($this->statement);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->statement && $this->valid;
    }
}
