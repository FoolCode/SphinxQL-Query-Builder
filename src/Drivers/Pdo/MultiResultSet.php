<?php

namespace Foolz\SphinxQL\Drivers\Pdo;


use Foolz\SphinxQL\Drivers\MultiResultSetInterface;
use Foolz\SphinxQL\Drivers\ResultSetException;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use PDOStatement;

class MultiResultSet implements MultiResultSetInterface
{
    /**
     * @var Connection
     */
    public $connection;

    /**
     * @var PDOStatement
     */
    public $statement;

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
    public $cursor = null;

    /**
     * @var \mysqli_result
     */
    public $current_set = null;

    /**
     * @param Connection $connection
     * @param PDOStatement $statement
     * @param int $count
     */
    public function __construct(Connection $connection, PDOStatement $statement, $count)
    {
        $this->connection = $connection;
        $this->statement = $statement;
        $this->count = $count;

        $this->store();
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
     * @throws ConnectionException
     */
    public function getPdoConnection()
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

        $store = array();
        while ($this->hasNextSet()) {
            // this relies on stored being null!
            $store[] = $this->toNextSet()->getSet()->store();
        }
        $this->cursor = null;

        // if we write the array straight to $this->stored it won't be null anymore and functions relying on null will break
        $this->stored = $store;

        return $this;
    }

    /**
     * Returns the stored result data
     *
     * @return ResultSet[]
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
     * Tells if we have more results
     *
     * @return bool
     */
    public function hasNextSet()
    {
        // if the cursor hasn't been used yet and there's at least a result
        // or if we haven't reached the last element yet
        return $this->cursor === null && $this->count > 0
            || $this->cursor < $this->count - 1;
    }

    public function toNextSet()
    {
        if (!$this->hasNextSet()) {
            throw new ResultSetException('There\'s no more results in this multiquery object.');
        }

        if ($this->stored !== null) {
            if ($this->cursor === null) {
                $this->cursor = 0;
            } else {
                $this->cursor++;
            }

            $this->current_set = $this->stored[$this->cursor];
        } else {
            // the first result is always already loaded
            if ($this->cursor === null) {
                $this->cursor = 0;
            } else {
                $this->cursor++;
                $this->statement->nextRowset();
            }

            $this->current_set = new ResultSet(
                $this->getConnection(),
                $this->statement
            );
        }

        return $this;
    }

    /**
     * Returns the currently pointed result
     *
     * @return ResultSet The next result
     * @throws ResultSetException If there isn't more results, use hasNextSet() to avoid this
     */
    public function getSet()
    {
        return $this->current_set;
    }

    /**
     * Call this to make sure there isn't pending results (else they'd appear in the next query)
     *
     * @return static $this
     */
    public function flush()
    {
        if ($this->stored !== null) {
            return $this;
        }

        while ($this->hasNextSet()) {
            $this->toNextSet()->getSet()->closeCursor();
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
        return $this->cursor >= 0 && $this->cursor < $this->getCount();
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

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->getSet();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        if ($this->hasNextSet()) {
            $this->toNextSet();
        } else {
            $this->cursor++;
        }
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return (int) $this->cursor;
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
        return $this->cursor >= 0 && $this->cursor < $this->getCount();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        // we actually can't roll this back unless it was stored first
        $this->cursor = null;
        $this->toNextSet();
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return $this->getCount();
    }
}
