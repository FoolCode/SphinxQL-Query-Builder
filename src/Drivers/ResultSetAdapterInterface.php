<?php

namespace Foolz\SphinxQL\Drivers;

interface ResultSetAdapterInterface
{
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
     * @param bool $assoc
     *
     * @return array|null
     */
    public function fetch($assoc = true);

    /**
     * @param bool $assoc
     *
     * @return array
     */
    public function fetchAll($assoc = true);
}
