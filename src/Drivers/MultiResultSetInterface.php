<?php
namespace Foolz\SphinxQL\Drivers;


interface MultiResultSetInterface extends \ArrayAccess, \Iterator, \Countable
{
    /**
     * Stores all the data in PHP and frees the data on the server
     *
     * @return static
     */
    public function store();

    /**
     * Returns the stored data as an array (results) of arrays (rows)
     *
     * @return ResultSetInterface[]
     */
    public function getStored();

    /**
     * Returns the total number of result sets
     *
     * @return mixed
     */
    public function getCount();

    /**
     * Tells whether there's more result sets
     *
     * @return bool True when there's more results, false otherwise
     */
    public function hasNextSet();

    /**
     * Moves the cursor to the next result set
     *
     * @return self
     */
    public function toNextSet();

    /**
     * Returns the current result set
     *
     * @return ResultSetInterface The result set pointed by the cursor
     */
    public function getSet();

    /**
     * Flushes the pending results that otherwise would appear in the next query
     *
     * @return static
     */
    public function flush();
}
