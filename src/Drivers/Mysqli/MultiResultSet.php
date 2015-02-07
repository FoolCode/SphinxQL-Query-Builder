<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;


use Foolz\SphinxQL\Drivers\DatabaseException;
use Foolz\SphinxQL\Drivers\MultiResultSetInterface;

class MultiResultSet implements MultiResultSetInterface
{
    /**
     * @var Connection
     */
    public $connection;

    /**
     * @var int
     */
    public $count;

    /**
     * @var null|array
     */
    public $stored = null;

    /**
     * @var int
     */
    public $cursor = 0;

    /**
     * @var \mysqli_result
     */
    public $current_set = null;

    /**
     * @param Connection $connection
     * @param int $count
     */
    public function __construct(Connection $connection, $count)
    {
        $this->connection = $connection;
        $this->count = $count;
    }

    /**
     * Returns the Mysqli\Connection wrapper
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the actual mysqli connection
     *
     * @return \mysqli
     * @throws \Foolz\SphinxQL\Drivers\ConnectionException
     */
    public function getMysqliConnection()
    {
        return $this->connection->getConnection();
    }

    /**
     * Stores all the data of the query in this object and frees the resources
     *
     * @return static $this
     * @throws DatabaseException Thrown when the method getNextSet() has already been called (server can't return all the data twice)
     */
    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        // don't let users mix storage and mysqli cursors
        if ($this->cursor > 0) {
            throw new DatabaseException('The MultiResultSet is using the mysqli cursors, store() can\'t fetch all the data');
        }

        $this->stored = array();
        while ($this->hasNextSet()) {
            // getStored also frees the set
            $this->stored[] = $this->getNextSet()->getStored();
        }

        return $this;
    }

    /**
     * Returns the stored result data
     *
     * @return array|null
     * @throws DatabaseException
     */
    public function getStored()
    {
        $this->store();
        return $this->stored;
    }

    /**
     * The number of results
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Returns the next result
     *
     * @return ResultSet The next result
     * @throws DatabaseException If there isn't more results, use hasNextSet() to avoid this
     */
    public function getNextSet()
    {
        if (!$this->hasNextSet()) {
            throw new DatabaseException('There\'s no more results in this multiquery object.');
        }

        // the first result is always already loaded
        if ($this->cursor > 0) {
            $this->getMysqliConnection()->next_result();
        }

        $this->cursor++;

        return new ResultSet(
            $this->getConnection(),
            $this->getMysqliConnection()->store_result()
        );
    }

    /**
     * Tells if we have more results
     *
     * @return bool
     */
    public function hasNextSet()
    {
        return $this->cursor < $this->count;
    }

    /**
     * Call this to make sure there isn't pending results (else they'd appear in the next query)
     *
     * @return static $this
     * @throws DatabaseException
     */
    public function flush()
    {
        while ($this->hasNextSet()) {
            $this->getNextSet()->freeResult();
        }

        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return $offset >= 0 && $this->hasNextSet();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        $this->store();
        return $this->stored[$offset];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Not implemented');
    }
}
