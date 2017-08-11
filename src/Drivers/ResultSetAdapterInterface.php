<?php

namespace Foolz\SphinxQL\Drivers;


interface ResultSetAdapterInterface
{
    CONST FETCH_NUM = 'num';
    CONST FETCH_ASSOC = 'assoc';


    public function getAffectedRows();

    public function getNumRows();

    public function getFields();

    public function isDml();

    public function store();

    public function toRow($num);

    public function freeResult();

    public function rewind();

    public function valid();

    public function fetch($fetch_type);

    public function fetchAll($fetch_type);
}
