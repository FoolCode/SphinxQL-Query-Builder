<?php

namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Drivers\Pdo\ResultSetAdapter as PdoResultSetAdapter;
use Foolz\SphinxQL\Exception\ResultSetException;

class ResultSet implements ResultSetInterface
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
    protected $stored;

    /**
     * @var null|array
     */
    protected $fetched;

    /**
     * @var ResultSetAdapterInterface
     */
    protected $adapter;

    /**
     * @param ResultSetAdapterInterface $adapter
     */
    public function __construct(ResultSetAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->init();

        if ($adapter instanceof PdoResultSetAdapter) { //почему то только для PDO
            $this->store();
        }
    }

    /**
     * @inheritdoc
     */
    public function hasRow($num)
    {
        return $num >= 0 && $num < $this->num_rows;
    }

    /**
     * @inheritdoc
     */
    public function hasNextRow()
    {
        return $this->cursor + 1 < $this->num_rows;
    }

    /**
     * @inheritdoc
     */
    public function getAffectedRows()
    {
        return $this->affected_rows;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return $this->hasRow($offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->toRow($offset)->fetchAssoc();
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
    public function current()
    {
        $row = $this->fetched;
        unset($this->fetched);

        return $row;
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        $this->fetched = $this->fetch(ResultSetAdapterInterface::FETCH_ASSOC);
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
    public function valid()
    {
        if ($this->stored !== null) {
            return $this->hasRow($this->cursor);
        }

        return $this->adapter->valid();
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        if ($this->stored === null) {
            $this->adapter->rewind();
        }

        $this->next_cursor = 0;

        $this->fetched = $this->fetch(ResultSetAdapterInterface::FETCH_ASSOC);
    }

    /**
     * Returns the number of rows in the result set
     * @inheritdoc
     */
    public function count()
    {
        return $this->num_rows;
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
     *
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
     * @param ResultSetAdapterInterface::FETCH_ASSOC|ResultSetAdapterInterface::FETCH_NUM $fetch_type
     *
     * @return array|bool|null
     */
    protected function fetchFromStore($fetch_type)
    {
        if ($this->stored === null) {
            return false;
        }

        $row = isset($this->stored[$this->cursor]) ? $this->stored[$this->cursor] : null;

        if ($row !== null) {
            $row = $fetch_type == ResultSetAdapterInterface::FETCH_ASSOC ? $this->makeAssoc($row) : $row;
        }

        return $row;
    }

    /**
     * @param ResultSetAdapterInterface::FETCH_ASSOC|ResultSetAdapterInterface::FETCH_NUM $fetch_type
     *
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
     * @param ResultSetAdapterInterface::FETCH_ASSOC|ResultSetAdapterInterface::FETCH_NUM $fetch_type
     *
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
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function toNextRow()
    {
        $this->toRow(++$this->cursor);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function fetchAllAssoc()
    {
        return $this->fetchAll(ResultSetAdapterInterface::FETCH_ASSOC);
    }

    /**
     * @inheritdoc
     */
    public function fetchAllNum()
    {
        return $this->fetchAll(ResultSetAdapterInterface::FETCH_NUM);
    }

    /**
     * @inheritdoc
     */
    public function fetchAssoc()
    {
        return $this->fetch(ResultSetAdapterInterface::FETCH_ASSOC);
    }

    /**
     * @inheritdoc
     */
    public function fetchNum()
    {
        return $this->fetch(ResultSetAdapterInterface::FETCH_NUM);
    }

    /**
     * @param ResultSetAdapterInterface::FETCH_ASSOC|ResultSetAdapterInterface::FETCH_NUM $fetch_type
     *
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
     * @inheritdoc
     */
    public function freeResult()
    {
        $this->adapter->freeResult();

        return $this;
    }
}
