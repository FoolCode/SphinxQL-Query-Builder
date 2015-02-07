<?php
namespace Foolz\SphinxQL\Drivers;


interface MultiResultSetInterface extends \ArrayAccess
{
    /**
     * Stores all the data in PHP and frees the data on the server
     *
     * @return void
     */
    public function store();

    /**
     * Returns the stored data as an array (results) of arrays (rows)
     *
     * @return array
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
     * Returns the next result set
     *
     * @return ResultSetInterface The next result set
     */
    public function getNextSet();

    /**
     * Flushes the pending results that otherwise would appear in the next query
     *
     * @return void
     */
    public function flush();
}
