<?php
namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\ResultSetException;
use \Foolz\SphinxQL\Drivers\Mysqli\ResultSetAdapter;

abstract class ResultSetBase implements ResultSetInterface
{
    /**
     * @var int
     */
    protected $num_rows = 0;

    /**
     * @var int
     */
    protected $cursor = 0;

    /**
     * @var int
     */
    protected $next_cursor = 0;

    /**
     * @var int
     */
    protected $affected_rows = 0; // leave to 0 so SELECT etc. will be coherent

    /**
     * @var array
     */
    protected $fields;

    /**
     * @var null|array
     */
    protected $stored = null;

    /**
     * @var null|array
     */
    protected $fetched = null;

    /**
     * @var null|\Foolz\SphinxQL\Drivers\ResultSetAdapterInterface
     */
    protected $adapter = null;

    /**
     * Checks that a row actually exists
     *
     * @param int $num The number of the row to check on
     * @return bool True if the row exists
     */
    public function hasRow($num)
    {
        return $num >= 0 && $num < $this->num_rows;
    }

    /**
     * Checks that a next row exists
     *
     * @return bool True if there's another row with a higher index
     */
    public function hasNextRow()
    {
        return $this->cursor + 1 < $this->num_rows;
    }

    /**
     * Returns the number of rows affected by the query
     * This will be 0 for SELECT and any query not editing rows
     *
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->affected_rows;
    }

    /**
     * Returns the number of rows in the result set
     *
     * @return int The number of rows in the result set
     */
    public function getCount()
    {
        return $this->num_rows;
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
        return $this->hasRow($offset);
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
        return $this->toRow($offset)->fetchAssoc();
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
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        $row = $this->fetched;
        unset($this->fetched);
        return $row;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->fetched = $this->fetch(ResultSetAdapter::FETCH_ASSOC);
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
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        if ($this->stored !== null) {
            return $this->hasRow($this->cursor);
        }

        return $this->adapter->valid();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        if ($this->stored === null) {
            $this->adapter->rewind();
        }

        $this->next_cursor = 0;

        $this->fetched = $this->fetch(ResultSetAdapter::FETCH_ASSOC);
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

    protected function init()
    {
        if ($this->adapter->isDml()) {
            $this->affected_rows = $this->adapter->getAffectedRows();
        } else {
            $this->num_rows = $this->adapter->getNumRows();
            $this->fields = $this->adapter->getFields();
        }
    }

    /**
     * @param array $numeric_array
     * @return array
     */
    protected function makeAssoc($numeric_array)
    {
        $assoc_array = array();
        foreach ($numeric_array as $col_key => $col_value) {
            $assoc_array[$this->fields[$col_key]->name] = $col_value;
        }

        return $assoc_array;
    }

    /**
     * @param ResultSetAdapter::FETCH_ASSOC|ResultSetAdapter::FETCH_NUM $fetch_type
     * @return array|bool|null
     */
    protected function fetchFromStore($fetch_type)
    {
        if ($this->stored === null) {
            return false;
        }

        $row = isset($this->stored[$this->cursor]) ? $this->stored[$this->cursor] : null;

        if ($row !== null) {
            $row = $fetch_type == ResultSetAdapter::FETCH_ASSOC ? $this->makeAssoc($row) : $row;
        }

        return $row;
    }

    /**
     * @param ResultSetAdapter::FETCH_ASSOC|ResultSetAdapter::FETCH_NUM $fetch_type
     * @return array|bool
     */
    protected function fetchAllFromStore($fetch_type)
    {
        if ($this->stored === null) {
            return false;
        }

        $result_from_store = array();

        $this->cursor = $this->next_cursor;
        while ($row = $this->fetchFromStore($fetch_type)) {
            $result_from_store[] = $row;
            $this->cursor = ++$this->next_cursor;
        }

        return $result_from_store;
    }

    /**
     * @param ResultSetAdapter::FETCH_ASSOC|ResultSetAdapter::FETCH_NUM $fetch_type
     * @return array
     */
    protected function fetchAll($fetch_type)
    {
        $fetch_all_result = $this->fetchAllFromStore($fetch_type);

        if ($fetch_all_result === false) {
            $fetch_all_result = $this->adapter->fetchAll($fetch_type);
        }

        $this->cursor = $this->num_rows;
        $this->next_cursor = $this->cursor + 1;

        return $fetch_all_result;
    }

    /**
     * Store all the data in this object and free the driver object
     *
     * @return static $this
     */
    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        if ($this->adapter->isDml()) {
            $this->stored = $this->affected_rows;
        } else {
            $this->stored = $this->adapter->store();
        }

        return $this;
    }

    /**
     * Returns the array as in version 0.9.x
     *
     * @return array|int|mixed
     * @deprecated Commodity method for simple transition to version 1.0.0
     */
    public function getStored()
    {
        $this->store();
        if ($this->adapter->isDml()) {
            return $this->getAffectedRows();
        }

        return $this->fetchAllAssoc();
    }

    /**
     * Moves the cursor to the selected row
     *
     * @param int $num The number of the row to move the cursor to
     * @return static
     * @throws ResultSetException If the row does not exist
     */
    public function toRow($num)
    {
        if (!$this->hasRow($num)) {
            throw new ResultSetException('The row does not exist.');
        }

        $this->cursor = $num;
        $this->next_cursor = $num;

        if ($this->stored === null) {
            $this->adapter->toRow($this->cursor);
        }

        return $this;
    }

    /**
     * Moves the cursor to the next row
     *
     * @return static $this
     * @throws ResultSetException If the next row does not exist
     */
    public function toNextRow()
    {
        $this->toRow(++$this->cursor);
        return $this;
    }

    /**
     * Fetches all the rows as an array of associative arrays
     *
     * @return array
     */
    public function fetchAllAssoc()
    {
        return $this->fetchAll(ResultSetAdapter::FETCH_ASSOC);
    }

    /**
     * Fetches all the rows as an array of indexed arrays
     *
     * @return array
     */
    public function fetchAllNum()
    {
        return $this->fetchAll(ResultSetAdapter::FETCH_NUM);
    }

    /**
     * Fetches a row as an associative array
     *
     * @return array
     */
    public function fetchAssoc()
    {
        return $this->fetch(ResultSetAdapter::FETCH_ASSOC);
    }

    /**
     * Fetches a row as an indexed array
     *
     * @return array|null
     */
    public function fetchNum()
    {
        return $this->fetch(ResultSetAdapter::FETCH_NUM);
    }

    /**
     * @param ResultSetAdapter::FETCH_ASSOC|ResultSetAdapter::FETCH_NUM $fetch_type
     * @return array|null
     */
    protected function fetch($fetch_type)
    {
        $this->cursor = $this->next_cursor;

        $row = $this->fetchFromStore($fetch_type);

        if ($row === false) {
            $row = $this->adapter->fetch($fetch_type);
        }

        $this->next_cursor++;

        return $row;
    }

    /**
     * Frees the memory from the result
     * Call it after you're done with a result set
     *
     * @return static
     */
    public function freeResult()
    {
        $this->adapter->freeResult();
        return $this;
    }
}
