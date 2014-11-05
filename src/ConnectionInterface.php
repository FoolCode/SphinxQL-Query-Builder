<?php

namespace Foolz\SphinxQL;

/**
 * Interface ConnectionInterface
 *
 * @package Foolz\SphinxQL
 */
interface ConnectionInterface
{
    /**
     * Performs a query on the Sphinx server.
     *
     * @param string $query The query string
     *
     * @return array|int The result array or number of rows affected
     * @throws \Foolz\SphinxQL\DatabaseException If the executed query produced an error
     */
    public function query($query);

    /**
     * Performs multiple queries on the Sphinx server.
     *
     * @param array $queue Queue holding all of the queries to be executed
     *
     * @return array The result array
     * @throws \Foolz\SphinxQL\DatabaseException In case a query throws an error
     * @throws \Foolz\SphinxQL\SphinxQLException In case the array passed is empty
     */
    public function multiQuery(Array $queue);

    /**
     * Escapes the input
     *
     * @param string $value The string to escape
     *
     * @return string The escaped string
     * @throws \Foolz\SphinxQL\DatabaseException If an error was encountered during server-side escape
     */
    public function escape($value);

    /**
     * Wraps the input with identifiers when necessary.
     *
     * @param \Foolz\SphinxQL\Expression|string $value The string to be quoted, or an Expression to leave it untouched
     *
     * @return \Foolz\SphinxQL\Expression|string The untouched Expression or the quoted string
     */
    public function quoteIdentifier($value);

    /**
     * Calls $this->quoteIdentifier() on every element of the array passed.
     *
     * @param array $array An array of strings to be quoted
     *
     * @return array The array of quoted strings
     */
    public function quoteIdentifierArr(Array $array = array());

    /**
     * Adds quotes around values when necessary.
     *
     * @param \Foolz\SphinxQL\Expression|string $value The input string, eventually wrapped in an expression to leave it untouched
     *
     * @return \Foolz\SphinxQL\Expression|string The untouched Expression or the quoted string
     */
    public function quote($value);

    /**
     * Calls $this->quote() on every element of the array passed.
     *
     * @param array $array The array of strings to quote
     *
     * @return array The array of quotes strings
     */
    public function quoteArr(Array $array = array());
}
