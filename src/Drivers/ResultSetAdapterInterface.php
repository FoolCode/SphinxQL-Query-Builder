<?php

namespace Foolz\SphinxQL\Drivers;

interface ResultSetAdapterInterface
{
    CONST FETCH_NUM = 'num';
    CONST FETCH_ASSOC = 'assoc';

    /**
     * @return int
     */
    public function getAffectedRows();

    /**
     * @return int
     */
    public function getNumRows();

    /**
     * @return array
     */
    public function getFields();

    /**
     * @return bool
     */
    public function isDml();

    /**
     * @return array
     */
    public function store();

    /**
     * @param int $num
     */
    public function toRow($num);

    /**
     * Free a result set/Closes the cursor, enabling the statement to be executed again.
     */
    public function freeResult();

    /**
     * Rewind to the first element
     */
    public function rewind();

    /**
     * @return bool
     */
    public function valid();

    /**
     * @param string $fetch_type self::FETCH_ASSOC|self::FETCH_NUM $fetch_type
     *
     * @return array|null
     */
    public function fetch($fetch_type);

    /**
     * @param string $fetch_type self::FETCH_ASSOC|self::FETCH_NUM $fetch_type
     *
     * @return array
     */
    public function fetchAll($fetch_type);
}
