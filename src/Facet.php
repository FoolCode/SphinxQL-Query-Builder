<?php

namespace Foolz\SphinxQL;

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Exception\SphinxQLException;

/**
 * Query Builder class for Facet statements.
 * @package Foolz\SphinxQL
 * @author Vicent Valls
 */
class Facet
{
    /**
     * A non-static connection for the current Facet object
     *
     * @var ConnectionInterface
     */
    protected $connection = null;

    /**
     * An SQL query that is not yet executed or "compiled"
     *
     * @var string
     */
    protected $query = null;

    /**
     * Array of select elements that will be comma separated.
     *
     * @var array
     */
    protected $facet = array();

    /**
     * BY array to be comma separated
     *
     * @var array
     */
    protected $by = array();

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected $order_by = array();

    /**
     * When not null it adds an offset
     *
     * @var null|int
     */
    protected $offset = null;

    /**
     * When not null it adds a limit
     *
     * @var null|int
     */
    protected $limit = null;

    public function __construct(ConnectionInterface $connection = null, $static = false)
    {
        $this->connection = $connection;
    }

    /**
     * Creates and setups a Facet object
     * The connection is required only in case this is not to be passed to a SphinxQL object via $sq->facet()
     *
     * @param ConnectionInterface|null $connection
     *
     * @return Facet
     */
    public static function create(ConnectionInterface $connection = null)
    {
        return new Facet($connection);
    }

    /**
     * Returns the currently attached connection
     *
     * @returns ConnectionInterface|null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Sets the connection to be used
     *
     * @param ConnectionInterface $connection
     * @return Facet
     */
    public function setConnection(ConnectionInterface $connection = null)
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Facet the columns
     *
     * Gets the arguments passed as $facet->facet('one', 'two')
     * Using it with array maps values as column names
     *
     * Examples:
     *    $query->facet('idCategory');
     *    // FACET idCategory
     *
     *    $query->facet('idCategory', 'year');
     *    // FACET idCategory, year
     *
     *    $query->facet(array('categories' => 'idCategory', 'year', 'type' => 'idType'));
     *    // FACET idCategory AS categories, year, idType AS type
     *
     * @param array|string $columns Array or multiple string arguments containing column names
     *
     * @return Facet
     */
    public function facet($columns = null)
    {
        if (!is_array($columns)) {
            $columns = \func_get_args();
        }

        foreach ($columns as $key => $column) {
            if (is_int($key)) {
                if (is_array($column)) {
                    $this->facet($column);
                } else {
                    $this->facet[] = array($column, null);
                }
            } else {
                $this->facet[] = array($column, $key);
            }
        }

        return $this;
    }

    /**
     * Facet a function
     *
     * Gets the function passed as $facet->facetFunction('FUNCTION', array('param1', 'param2', ...))
     *
     * Examples:
     *    $query->facetFunction('category');
     *
     * @param string       $function Function name
     * @param array|string $params   Array or multiple string arguments containing column names
     *
     * @return Facet
     */
    public function facetFunction($function, $params = null)
    {
        if (is_array($params)) {
            $params = implode(',', $params);
        }

        $this->facet[] = new Expression($function.'('.$params.')');

        return $this;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param string $column A column to group by
     *
     * @return Facet
     */
    public function by($column)
    {
        $this->by = $column;

        return $this;
    }

    /**
     * ORDER BY clause
     * Adds to the previously added columns
     *
     * @param string $column    The column to order on
     * @param string $direction The ordering direction (asc/desc)
     *
     * @return Facet
     */
    public function orderBy($column, $direction = null)
    {
        $this->order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * Facet a function
     *
     * Gets the function passed as $facet->facetFunction('FUNCTION', array('param1', 'param2', ...))
     *
     * Examples:
     *    $query->facetFunction('category');
     *
     * @param string       $function  Function name
     * @param array        $params    Array  string arguments containing column names
     * @param string       $direction The ordering direction (asc/desc)
     *
     * @return Facet
     */
    public function orderByFunction($function, $params = null, $direction = null)
    {
        if (is_array($params)) {
            $params = implode(',', $params);
        }

        $this->order_by[] = array('column' => new Expression($function.'('.$params.')'), 'direction' => $direction);

        return $this;
    }

    /**
     * LIMIT clause
     * Supports also LIMIT offset, limit
     *
     * @param int      $offset Offset if $limit is specified, else limit
     * @param null|int $limit  The limit to set, null for no limit
     *
     * @return Facet
     */
    public function limit($offset, $limit = null)
    {
        if ($limit === null) {
            $this->limit = (int) $offset;
            return $this;
        }

        $this->offset($offset);
        $this->limit = (int) $limit;

        return $this;
    }

    /**
     * OFFSET clause
     *
     * @param int $offset The offset
     *
     * @return Facet
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;

        return $this;
    }

    /**
     * Compiles the statements for FACET
     *
     * @return Facet
     * @throws SphinxQLException In case no column in facet
     */
    public function compileFacet()
    {
        $query = 'FACET ';

        if (!empty($this->facet)) {
            $facets = array();
            foreach ($this->facet as $array) {
                if ($array instanceof Expression) {
                    $facets[] = $array;
                } else if ($array[1] === null) {
                    $facets[] = $this->getConnection()->quoteIdentifier($array[0]);
                } else {
                    $facets[] = $this->getConnection()->quoteIdentifier($array[0]).' AS '.$array[1];
                }
            }
            $query .= implode(', ', $facets).' ';
        } else {
            throw new SphinxQLException('There is no column in facet.');
        }

        if (!empty($this->by)) {
            $query .= 'BY '.$this->getConnection()->quoteIdentifier($this->by).' ';
        }

        if (!empty($this->order_by)) {
            $query .= 'ORDER BY ';

            $order_arr = array();

            foreach ($this->order_by as $order) {
                $order_sub = $this->getConnection()->quoteIdentifier($order['column']).' ';
                $order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');

                $order_arr[] = $order_sub;
            }

            $query .= implode(', ', $order_arr).' ';
        }

        if ($this->limit !== null || $this->offset !== null) {
            if ($this->offset === null) {
                $this->offset = 0;
            }

            if ($this->limit === null) {
                $this->limit = 9999999999999;
            }

            $query .= 'LIMIT '.((int) $this->offset).', '.((int) $this->limit).' ';
        }

        $this->query = trim($query);

        return $this;
    }

    /**
     * Get String with SQL facet
     *
     * @return string
     */
    public function getFacet()
    {
        return $this->compileFacet()->query;
    }
}
