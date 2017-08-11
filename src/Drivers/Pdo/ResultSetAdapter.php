<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use \PDO;
use \PDOStatement;

class ResultSetAdapter implements \Foolz\SphinxQL\Drivers\ResultSetAdapterInterface
{
    /**
     * @var null|PDOStatement
     */
    protected $statement = null;

    /**
     * @var bool
     */
    protected $valid = true;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->statement->rowCount();
    }

    /**
     * @return int
     */
    public function getNumRows()
    {
        return $this->statement->rowCount();
    }

    /**
     * @return array
     */
    public function getFields()
    {
        $fields = array();

        for ($i = 0; $i < $this->statement->columnCount(); $i++) {
            $fields[] = (object)$this->statement->getColumnMeta($i);
        }

        return $fields;
    }

    /**
     * @return bool
     */
    public function isDml()
    {
        return $this->statement->columnCount() == 0;
    }

    /**
     * @return array
     */
    public function store()
    {
        return $this->statement->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * @param $num
     */
    public function toRow($num)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function freeResult()
    {
        $this->statement->closeCursor();
    }

    public function rewind()
    {

    }

    /**
     * @return bool
     */
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
            $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        } else {
            $row = $this->statement->fetch(PDO::FETCH_NUM);
        }

        if (!$row) {
            $this->valid = false;
            $row = null;
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
            $row = $this->statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $row = $this->statement->fetchAll(PDO::FETCH_NUM);
        }

        if (empty($row)) {
            $this->valid = false;
        }

        return $row;
    }
}
