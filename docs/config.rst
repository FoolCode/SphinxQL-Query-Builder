.. _config:

Configuration
=============

Obtaining a Connection
----------------------

You can obtain a SphinxQL Connection with the `Foolz\\SphinxQL\\Drivers\\Mysqli\\Connection` class.

.. code-block:: php

    <?php

    use Foolz\SphinxQL\Drivers\Mysqli\Connection;

    $conn = new Connection();
    $conn->setparams(array('host' => '127.0.0.1', 'port' => 9306));

.. warning::

    The existing PDO driver written is considered experimental as the behaviour changes between certain PHP releases.

Connection Parameters
---------------------

The connection parameters provide information about the instance you wish to establish a connection with. The parameters required is set with the `setParams($array)` or `setParam($key, $value)` methods.

    .. describe:: host

        :Type: string
        :Default: 127.0.0.1

    .. describe:: port

        :Type: int
        :Default: 9306

    .. describe:: socket

        :Type: string
        :Default: null

    .. describe:: options

        :Type: array
        :Default: null
