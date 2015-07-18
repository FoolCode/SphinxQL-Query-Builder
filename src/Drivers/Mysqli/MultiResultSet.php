<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;


use Foolz\SphinxQL\Drivers\MultiResultSetBase;
use Foolz\SphinxQL\Exception\DatabaseException;

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
        $this->connection = $connection;
    }

    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        // don't let users mix storage and mysqli cursors
        if ($this->cursor !== null) {
            throw new DatabaseException('The MultiResultSet is using the mysqli cursors, store() can\'t fetch all the data');
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
                if (!$this->connection->getConnection()->more_results()) {
                    return false;
                }

                $this->connection->getConnection()->next_result();
            }

            return new ResultSet(
                $this->connection,
                $this->connection->getConnection()->store_result()
            );
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
        if ($this->stored !== null) {
            return $this->stored[(int) $this->cursor];
        }
        return $this->getNext();
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
        if ($this->stored !== null) {
            return $this->cursor >= 0 && $this->cursor < count($this->stored);
        }

        return $this->cursor >= 0 && $this->connection->getConnection()->more_results();
    }
}
