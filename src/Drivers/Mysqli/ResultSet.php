<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Drivers\ResultSetInterface;

class ResultSet implements ResultSetInterface, \ArrayAccess
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var \mysqli_result
     */
    protected $result;

    /**
     * @var int
     */
    protected $num_rows = 0;

    /**
     * @var null|array
     */
    protected $stored = null;

    /**
     * @var int
     */
    protected $affected_rows = 0; // leave to 0 so SELECT etc. will be coherent

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @var null|array
     */
    protected $current_row = null;

    /**
     * @param Connection $connection
     * @param null $result
     */
    public function __construct(Connection $connection, $result = null)
    {
        $this->connection = $connection;

        if ($result instanceof \mysqli_result) {
            $this->result = $result;
            $this->num_rows = $this->result->num_rows;
        } else {
            $this->affected_rows = $this->getMysqliConnection()->affected_rows;
        }
    }

    public function nextRow() {
        $this->position++;
        $this->result->fetch_assoc();
    }

    public function fetchAssoc() {
        $this->result->fetch_assoc();
    }

    /**
     * Store all the data in this object and free the mysqli object
     *
     * @return $this
     */
    public function store()
    {
        if ($this->result instanceof \mysqli_result) {
            $result = array();

            while ($row = $this->result->fetch_assoc()) {
                $result[] = $row;
            }

            $this->result->free_result();
            $this->stored = $result;
        } else {
            $this->stored = $this->affected_rows;
        }

        return $this;
    }

    public function getStored() {
        if ($this->stored === null) {
            $this->store();
        }

        return $this->stored;
    }

    /**
     * Get the result object returned by PHP's MySQLi
     *
     * @return \mysqli_result
     */
    public function getResultObject()
    {
        return $this->result;
    }

    /**
     * Get the MySQLi connection wrapper object
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the PHP MySQLi object
     *
     * @return \mysqli
     * @throws \Foolz\SphinxQL\Drivers\ConnectionException
     */
    public function getMysqliConnection()
    {
        return $this->connection->getConnection();
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

    public function count()
    {
        return $this->num_rows;
    }

    /**
     * Frees the memory from the result
     * Call it after you're done with a result set
     */
    public function freeResult()
    {
        $this->result->free_result();
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
        return $offset >= 0 && ($this->num_rows - 1) < $offset;
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
        if ($this->stored) {
            return $this->stored[$offset];
        }

        $this->result->data_seek($offset);
        return $this->result->fetch_assoc();
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
