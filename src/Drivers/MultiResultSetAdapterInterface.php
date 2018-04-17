<?php

namespace Foolz\SphinxQL\Drivers;

interface MultiResultSetAdapterInterface
{
    /**
     * Advances to the next rowset
     */
    public function getNext();

    /**
     * @return ResultSetInterface
     */
    public function current();

    /**
     * @return bool
     */
    public function valid();
}
