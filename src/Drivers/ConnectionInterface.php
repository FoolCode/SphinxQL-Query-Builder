<?php

namespace Foolz\SphinxQL\Drivers;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Expression;

interface ConnectionInterface
{
    /**
     * Performs a query on the Sphinx server.
     *
     * @param string $query The query string
     *
     * @return ResultSetInterface The result array or number of rows affected
     * @throws DatabaseException If the executed query produced an error
     * @throws ConnectionException
     */
    public function query($query);

    /**
     * Performs multiple queries on the Sphinx server.
     *
     * @param array $queue Queue holding all of the queries to be executed
     *
     * @return MultiResultSetInterface The result array
     * @throws DatabaseException In case a query throws an error
     * @throws SphinxQLException In case the array passed is empty
     * @throws ConnectionException
     */
    public function multiQuery(array $queue);

    /**
     * Escapes the input
     *
     * @param string $value The string to escape
     *
     * @return string The escaped string
     * @throws DatabaseException If an error was encountered during server-side escape
     * @throws ConnectionException
     */
    public function escape($value);

    /**
     * Adds quotes around values when necessary.
     *
     * @param Expression|string|null|bool|array|int|float $value The input string, eventually wrapped in an expression
     *      to leave it untouched
     *
     * @return Expression|string|int The untouched Expression or the quoted string
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function quote($value);

    /**
     * Calls $this->quote() on every element of the array passed.
     *
     * @param array $array The array of elements to quote
     *
     * @return array The array of quotes elements
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function quoteArr(array $array = array());
}
