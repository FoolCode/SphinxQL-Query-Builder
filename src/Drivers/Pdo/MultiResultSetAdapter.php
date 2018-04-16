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

    /**
     * @inheritdoc
     */
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
     * @inheritdoc
     */
    public function current()
    {
        return new ResultSet($this->statement);
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return $this->statement && $this->valid;
    }
}
