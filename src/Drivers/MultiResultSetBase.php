<?php
namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\DatabaseException;

abstract class MultiResultSetBase implements MultiResultSetInterface
{
    /**
     * @var null|array
     */
    public $stored = null;

    /**
     * @var int
     */
    public $cursor = 0;

    /**
     * @var int
     */
    protected $next_cursor = 0;

    /**
     * @var \Foolz\SphinxQL\Drivers\ResultSetInterface|null
     */
    protected $rowSet = null;

    /**
     * @var \Foolz\SphinxQL\Drivers\MultiResultSetAdapterInterface|null
     */
    protected $adapter = null;

    /**
     * @var bool
     */
    protected $valid = true;

    public function getStored()
    {
        $this->store();
        return $this->stored;
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
        $this->store();
        return $this->storedValid($offset);
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
     *
     * @codeCoverageIgnore
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
     *
     * @codeCoverageIgnore
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->rowSet = $this->getNext();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return (int)$this->cursor;
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
        $this->cursor = 0;
        $this->next_cursor = 0;
        $this->rowSet = $this->getNext();
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
        $this->store();
        return count($this->stored);
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
            return $this->storedValid();
        }

        return $this->adapter->valid();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        $rowSet = $this->rowSet;
        unset($this->rowSet);
        return $rowSet;
    }

    /**
     * @param null|int $cursor
     * @return bool
     */
    protected function storedValid($cursor = null)
    {
        $cursor = (!is_null($cursor) ? $cursor : $this->cursor);
        return $cursor >= 0 && $cursor < count($this->stored);
    }

    /*
     * @return \Foolz\SphinxQL\Drivers\ResultSetInterface|false
     */
    public function getNext()
    {
        $this->cursor = $this->next_cursor;

        if ($this->stored !== null) {
            $resultSet = !$this->storedValid() ? false : $this->stored[$this->cursor];
        } else {
            if ($this->next_cursor > 0) {
                $this->adapter->getNext();
            }

            $resultSet = !$this->adapter->valid() ? false : $this->adapter->current();
        }

        $this->next_cursor++;

        return $resultSet;
    }

    /**
     * @return $this
     * @throws DatabaseException
     */
    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        // don't let users mix storage and driver cursors
        if ($this->next_cursor > 0) {
            throw new DatabaseException('The MultiResultSet is using the driver cursors, store() can\'t fetch all the data');
        }

        $store = array();
        while ($set = $this->getNext()) {
            // this relies on stored being null!
            $store[] = $set->store();
        }

        $this->cursor = 0;
        $this->next_cursor = 0;

        // if we write the array straight to $this->stored it won't be null anymore and functions relying on null will break
        $this->stored = $store;

        return $this;
    }
}
