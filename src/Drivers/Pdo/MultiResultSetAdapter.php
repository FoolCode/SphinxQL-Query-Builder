<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

class MultiResultSetAdapter implements \Foolz\SphinxQL\Drivers\MultiResultSetAdapterInterface
{
    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @var \PDOStatement
     */
    protected $statement = null;

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
        return ResultSet::make($this->statement);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->statement && $this->valid;
    }
}
