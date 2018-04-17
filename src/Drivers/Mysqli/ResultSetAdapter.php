<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Drivers\ResultSetAdapterInterface;
use Foolz\SphinxQL\Exception\ConnectionException;
use mysqli_result;

class ResultSetAdapter implements ResultSetAdapterInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var mysqli_result|bool
     */
    protected $result;

    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @param Connection         $connection
     * @param mysqli_result|bool $result
     */
    public function __construct(Connection $connection, $result)
    {
        $this->connection = $connection;
        $this->result = $result;
    }

    /**
     * @inheritdoc
     * @throws ConnectionException
     */
    public function getAffectedRows()
    {
        return $this->connection->getConnection()->affected_rows;
    }

    /**
     * @inheritdoc
     */
    public function getNumRows()
    {
        return $this->result->num_rows;
    }

    /**
     * @inheritdoc
     */
    public function getFields()
    {
        return $this->result->fetch_fields();
    }

    /**
     * @inheritdoc
     */
    public function isDml()
    {
        return !($this->result instanceof mysqli_result);
    }

    /**
     * @inheritdoc
     */
    public function store()
    {
        $this->result->data_seek(0);

        return $this->result->fetch_all(MYSQLI_NUM);
    }

    /**
     * @inheritdoc
     */
    public function toRow($num)
    {
        $this->result->data_seek($num);
    }

    /**
     * @inheritdoc
     */
    public function freeResult()
    {
        $this->result->free_result();
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        $this->valid = true;
        $this->result->data_seek(0);
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return $this->valid;
    }

    /**
     * @inheritdoc
     */
    public function fetch($assoc = true)
    {
        if ($assoc) {
            $row = $this->result->fetch_assoc();
        } else {
            $row = $this->result->fetch_row();
        }

        if (!$row) {
            $this->valid = false;
        }

        return $row;
    }

    /**
     * @inheritdoc
     */
    public function fetchAll($assoc = true)
    {
        if ($assoc) {
            $row = $this->result->fetch_all(MYSQLI_ASSOC);
        } else {
            $row = $this->result->fetch_all(MYSQLI_NUM);
        }

        if (empty($row)) {
            $this->valid = false;
        }

        return $row;
    }
}
