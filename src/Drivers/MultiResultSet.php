<?php

namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\DatabaseException;

class MultiResultSet implements MultiResultSetInterface
{
    /**
     * @var null|array
     */
    protected $stored;

    /**
     * @var int
     */
    protected $cursor = 0;

    /**
     * @var int
     */
    protected $next_cursor = 0;

    /**
     * @var ResultSetInterface|null
     */
    protected $rowSet;

    /**
     * @var MultiResultSetAdapterInterface
     */
    protected $adapter;

    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @param MultiResultSetAdapterInterface $adapter
     */
    public function __construct(MultiResultSetAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @inheritdoc
     * @throws DatabaseException
     */
    public function getStored()
    {
        $this->store();

        return $this->stored;
    }

    /**
     * @inheritdoc
     * @throws DatabaseException
     */
    public function offsetExists($offset)
    {
        $this->store();

        return $this->storedValid($offset);
    }

    /**
     * @inheritdoc
     * @throws DatabaseException
     */
    public function offsetGet($offset)
    {
        $this->store();

        return $this->stored[$offset];
    }

    /**
     * @inheritdoc
     * @codeCoverageIgnore
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * @inheritdoc
     * @codeCoverageIgnore
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        $this->rowSet = $this->getNext();
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return (int)$this->cursor;
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // we actually can't roll this back unless it was stored first
        $this->cursor = 0;
        $this->next_cursor = 0;
        $this->rowSet = $this->getNext();
    }

    /**
     * @inheritdoc
     * @throws DatabaseException
     */
    public function count()
    {
        $this->store();

        return count($this->stored);
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        if ($this->stored !== null) {
            return $this->storedValid();
        }

        return $this->adapter->valid();
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        $rowSet = $this->rowSet;
        unset($this->rowSet);

        return $rowSet;
    }

    /**
     * @param null|int $cursor
     *
     * @return bool
     */
    protected function storedValid($cursor = null)
    {
        $cursor = (!is_null($cursor) ? $cursor : $this->cursor);

        return $cursor >= 0 && $cursor < count($this->stored);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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
