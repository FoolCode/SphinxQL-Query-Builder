<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\ResultSetException;
use Foolz\SphinxQL\Drivers\ResultSetBase;

class ResultSet extends ResultSetBase
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
     * @param Connection $connection
     * @param null|\mysqli_result $result
     */
    public function __construct(Connection $connection, $result = null)
    {
        $this->connection = $connection;

        if ($result instanceof \mysqli_result) {
            $this->result = $result;
            $this->num_rows = $this->result->num_rows;
            $this->fields = $this->result->fetch_fields();
        } else {
            $this->affected_rows = $this->getMysqliConnection()->affected_rows;
        }
    }

    /**
     * Store all the data in this object and free the mysqli object
     *
     * @return static $this
     */
    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        if ($this->result instanceof \mysqli_result) {
            $result = $this->result->fetch_all(MYSQLI_NUM);
            $this->stored = $result;
        } else {
            $this->stored = $this->affected_rows;
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
        if (!($this->result instanceof \mysqli_result)) {
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

        $this->current_row = $num;
        $this->result->data_seek($num);
        $this->fetched = $this->result->fetch_row();

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
        if (!$this->hasNextRow()) {
            throw new ResultSetException('The next row does not exist.');
        }

        if ($this->current_row === null) {
            $this->current_row = 0;
        } else {
            $this->current_row++;
        }

        $this->fetched = $this->result->fetch_row();

        return $this;
    }

    /**
     * Fetches all the rows as an array of associative arrays
     *
     * @return array|mixed
     */
    public function fetchAllAssoc() {
        if ($this->stored !== null) {
            $result = array();
            foreach ($this->stored as $row_key => $row_value) {
                foreach ($row_value as $col_key => $col_value) {
                    $result[$row_key][$this->fields[$col_key]->name] = $col_value;
                }
            }

            return $result;
        }

        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Fetches all the rows as an array of indexed arrays
     *
     * @return array|mixed|null
     */
    public function fetchAllNum() {
        if ($this->stored !== null) {
            return $this->stored;
        }

        return $this->result->fetch_all(MYSQLI_NUM);
    }

    /**
     * Fetches a row as an associative array
     *
     * @return array
     */
    public function fetchAssoc() {
        if ($this->stored) {
            $row = $this->stored[$this->current_row];
        } else {
            $row = $this->fetched;
        }

        $result = array();
        foreach ($row as $col_key => $col_value) {
            $result[$this->fields[$col_key]->name] = $col_value;
        }

        return $result;
    }

    /**
     * Fetches a row as an indexed array
     *
     * @return array|null
     */
    public function fetchNum() {
        if ($this->stored) {
            return $this->stored[$this->current_row];
        } else {
            return $this->fetched;
        }
    }

    /**
     * Get the result object returned by PHP's MySQLi
     *
     * @return \mysqli_result
     *
     * @codeCoverageIgnore
     */
    public function getResultObject()
    {
        return $this->result;
    }

    /**
     * Get the MySQLi connection wrapper object
     *
     * @return Connection
     *
     * @codeCoverageIgnore
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the PHP MySQLi object
     *
     * @return \mysqli
     * @throws ConnectionException
     */
    public function getMysqliConnection()
    {
        return $this->connection->getConnection();
    }

    /**
     * Frees the memory from the result
     * Call it after you're done with a result set
     *
     * @return static
     */
    public function freeResult()
    {
        $this->result->free_result();
        return $this;
    }
}
