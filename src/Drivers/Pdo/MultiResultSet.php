<?php

namespace Foolz\SphinxQL\Drivers\Pdo;


use Foolz\SphinxQL\Drivers\MultiResultSetBase;
use Foolz\SphinxQL\Exception\DatabaseException;
use PDOStatement;

class MultiResultSet extends MultiResultSetBase
{
    /**
     * @var PDOStatement
     */
    public $statement;

    /**
     * @param PDOStatement|array|int $statement
     */
    public function __construct($statement)
    {
        if ($statement instanceof PDOStatement) {
            $this->statement = $statement;
        } else {
            $this->stored = $statement; // for php < 5.4.0
        }
    }

    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        // don't let users mix storage and pdo cursors
        if ($this->cursor !== null) {
            throw new DatabaseException('The MultiResultSet is using the pdo cursors, store() can\'t fetch all the data');
        }

        $store = array();
        while ($set = $this->getNext()) {
            // this relies on stored being null!
            $store[] = $set->store();
        }

        $this->cursor = null;

        // if we write the array straight to $this->stored it won't be null anymore and functions relying on null will break
        $this->stored = $store;

        return $this;
    }

    public function getNext()
    {
        if ($this->stored !== null) {
            if ($this->cursor === null) {
                $this->cursor = 0;
            } else {
                $this->cursor++;
            }

            if ($this->cursor >= count($this->stored)) {
                return false;
            }

            return $this->stored[$this->cursor];
        } else {
            // the first result is always already loaded
            if ($this->cursor === null) {
                $this->cursor = 0;
            } else {
                $this->cursor++;
                $res = $this->statement->nextRowset();
                if (!$res) {
                    return false;
                }
            }

            return new ResultSet($this->statement);
        }
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        $this->store();
        return $this->stored[(int) $this->cursor];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        $this->store();
        return $this->cursor >= 0 && $this->cursor < count($this->stored);
    }
}
