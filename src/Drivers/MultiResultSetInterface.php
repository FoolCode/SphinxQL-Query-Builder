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
     * Returns the next result set, or false if there's no more results
     *
     * @return ResultSetInterface|false
     */
    public function getNext();
}
