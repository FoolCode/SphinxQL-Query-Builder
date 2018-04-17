<?php

namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\ResultSetException;

interface ResultSetInterface extends \ArrayAccess, \Iterator, \Countable
{
    /**
     * Stores all the result data in the object and frees the database results
     *
     * @return $this
     */
    public function store();

    /**
     * Returns the array as in version 0.9.x
     *
     * @return array|int
     * @deprecated Commodity method for simple transition to version 1.0.0
     */
    public function getStored();

    /**
     * Checks if the specified row exists
     *
     * @param int $row The number of the row to check on
     *
     * @return bool True if the row exists, false otherwise
     */
    public function hasRow($row);

    /**
     * Moves the cursor to the specified row
     *
     * @param int $row The row to move the cursor to
     *
     * @return $this
     * @throws ResultSetException If the row does not exist
     */
    public function toRow($row);

    /**
     * Checks if the next row exists
     *
     * @return bool True if the row exists, false otherwise
     */
    public function hasNextRow();

    /**
     * Moves the cursor to the next row
     *
     * @return $this
     * @throws ResultSetException If the next row does not exist
     */
    public function toNextRow();

    /**
     * Returns the number of affected rows
     * This will be 0 for SELECT and any query not editing rows
     *
     * @return int
     */
    public function getAffectedRows();

    /**
     * Fetches all the rows as an array of associative arrays
     *
     * @return array An array of associative arrays
     */
    public function fetchAllAssoc();

    /**
     * Fetches all the rows as an array of indexed arrays
     *
     * @return array An array of indexed arrays
     */
    public function fetchAllNum();

    /**
     * Fetches all the rows the cursor points to as an associative array
     *
     * @return array|null An associative array representing the row
     */
    public function fetchAssoc();

    /**
     * Fetches all the rows the cursor points to as an indexed array
     *
     * @return array|null An indexed array representing the row
     */
    public function fetchNum();

    /**
     * Frees the database from the result
     * Call it after you're done with a result set
     *
     * @return $this
     */
    public function freeResult();
}
