<?php

namespace Foolz\SphinxQL\Drivers;

class ResultSetException extends \Exception {}

interface ResultSetInterface extends \ArrayAccess, \Iterator
{

}
