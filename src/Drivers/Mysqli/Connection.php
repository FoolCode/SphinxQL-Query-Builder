<?php

namespace Foolz\SphinxQL\Drivers\Mysqli;

use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Drivers\MultiResultSet;
use Foolz\SphinxQL\Drivers\ResultSet;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;

/**
 * SphinxQL connection class utilizing the MySQLi extension.
 * It also contains escaping and quoting functions.
 */
class Connection extends ConnectionBase
{
    /**
     * Internal Encoding
     *
     * @var string
     */
    protected $internal_encoding;

    /**
     * Returns the internal encoding.
     *
     * @return string current multibyte internal encoding
     */
    public function getInternalEncoding()
    {
        return $this->internal_encoding;
    }

    /**
     * @inheritdoc
     */
    public function connect()
    {
        $data = $this->getParams();
        $conn = mysqli_init();

        if (!empty($data['options'])) {
            foreach ($data['options'] as $option => $value) {
                $conn->options($option, $value);
            }
        }

        set_error_handler(function () {});
        try {
            if (!$conn->real_connect($data['host'], null, null, null, (int) $data['port'], $data['socket'])) {
                throw new ConnectionException('Connection Error: ['.$conn->connect_errno.']'.$conn->connect_error);
            }
        } finally {
            restore_error_handler();
        }

        $conn->set_charset('utf8');
        $this->connection = $conn;
        $this->mbPush();

        return true;
    }

    /**
     * Pings the Sphinx server.
     *
     * @return bool True if connected, false otherwise
     * @throws ConnectionException
     */
    public function ping()
    {
        $this->ensureConnection();

        return $this->getConnection()->ping();
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        $this->mbPop();
        $this->getConnection()->close();

        return parent::close();
    }

    /**
     * @inheritdoc
     */
    public function query($query)
    {
        $this->ensureConnection();

        set_error_handler(function () {});
        try {
            /**
             * ManticoreSearch/Sphinx silence warnings thrown by php mysqli/mysqlnd
             *
             * unknown command (code=9) - status() command not implemented by Sphinx/ManticoreSearch
             * ERROR mysqli::prepare(): (08S01/1047): unknown command (code=22) - prepare() not implemented by Sphinx/Manticore
             */
            $resource = @$this->getConnection()->query($query);
        } finally {
            restore_error_handler();
        }        

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.$query.']');
        }

        return new ResultSet(new ResultSetAdapter($this, $resource));
    }

    /**
     * @inheritdoc
     */
    public function multiQuery(array $queue)
    {
        $count = count($queue);

        if ($count === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        $this->ensureConnection();

        $this->getConnection()->multi_query(implode(';', $queue));

        if ($this->getConnection()->error) {
            throw new DatabaseException('['.$this->getConnection()->errno.'] '.
                $this->getConnection()->error.' [ '.implode(';', $queue).']');
        }

        return new MultiResultSet(new MultiResultSetAdapter($this));
    }

    /**
     * Escapes the input with \MySQLi::real_escape_string.
     * Based on FuelPHP's escaping function.
     * @inheritdoc
     */
    public function escape($value)
    {
        $this->ensureConnection();

        if (($value = $this->getConnection()->real_escape_string((string) $value)) === false) {
            // @codeCoverageIgnoreStart
            throw new DatabaseException($this->getConnection()->error, $this->getConnection()->errno);
            // @codeCoverageIgnoreEnd
        }

        return "'".$value."'";
    }

    /**
     * Enter UTF-8 multi-byte workaround mode.
     */
    public function mbPush()
    {
        $this->internal_encoding = mb_internal_encoding();
        mb_internal_encoding('UTF-8');

        return $this;
    }

    /**
     * Exit UTF-8 multi-byte workaround mode.
     */
    public function mbPop()
    {
        // TODO: add test case for #155
        if ($this->getInternalEncoding()) {
            mb_internal_encoding($this->getInternalEncoding());
            $this->internal_encoding = null;
        }

        return $this;
    }
}
