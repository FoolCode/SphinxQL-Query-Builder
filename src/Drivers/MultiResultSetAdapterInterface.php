<?php

namespace Foolz\SphinxQL\Drivers;


interface MultiResultSetAdapterInterface
{
    public function getNext();

    public function current();

    public function valid();
}
