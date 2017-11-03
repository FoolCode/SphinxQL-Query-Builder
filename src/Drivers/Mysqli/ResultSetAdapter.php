<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

class ResultSetAdapter implements \Foolz\SphinxQL\Drivers\ResultSetAdapterInterface
{
    /**
     * @var Connection|null
     */
    protected $connection = null;

    /**
     * @var \mysqli_result|null
     */
    protected $result = null;

    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @param Connection $connection
     * @param null|\mysqli_result $result
     */
    public function __construct(Connection $connection, $result = null)
    {
        $this->connection = $connection;
        $this->result = $result;
    }

    /**
     * @return mixed
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     */
    public function getAffectedRows()
    {
        return $this->connection->getConnection()->affected_rows;
    }

    /**
     * @return int
     */
    public function getNumRows()
    {
        return $this->result->num_rows;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->result->fetch_fields();
    }

    /**
     * @return bool
     */
    public function isDml()
    {
        return !($this->result instanceof \mysqli_result);
    }

    /**
     * @return mixed
     */
    public function store()
    {
        $this->result->data_seek(0);
        return $this->result->fetch_all(MYSQLI_NUM);
    }

    /**
     * @param int $num
     */
    public function toRow($num)
    {
        $this->result->data_seek($num);
    }

    public function freeResult()
    {
        $this->result->free_result();
    }

    public function rewind()
    {
        $this->valid = true;
        $this->result->data_seek(0);
    }

    public function valid()
    {
        return $this->valid;
    }

    /**
     * @param self::FETCH_ASSOC|self::FETCH_NUM $fetch_type
     * @return array|null
     */
    public function fetch($fetch_type)
    {
        if ($fetch_type == self::FETCH_ASSOC) {
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
     * @param self::FETCH_ASSOC|self::FETCH_NUM $fetch_type
     * @return array
     */
    public function fetchAll($fetch_type)
    {
        if ($fetch_type == self::FETCH_ASSOC) {
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
