<?php
/**
 * Created by PhpStorm.
 * User: Vizzent
 * Date: 16/02/15
 * Time: 9:04
 */

namespace Foolz\SphinxQL;


use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Exception\SphinxQLException;

class Facet {

    /**
     * A non-static connection for the current SphinxQL object
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
     * Creates and setups a SphinxQL object
     *
     * @param ConnectionInterface $connection
     *
     * @return Facet The current object
     */
    public static function create(ConnectionInterface $connection)
    {
        return new Facet($connection);
    }

    /**
     * Returns the currently attached connection
     *
     * @returns ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Facet the columns
     *
     * Gets the arguments passed as $facet->facet('one', 'two')
     * Using it with array maps values as column names
     *
     * Examples:
     * ->facet('idCategory');
     *
     * ->facet('idCategory', 'year');
     *    // FACET idCategory, year
     *
     * ->facet(array('categories' => 'idCategory', 'year', 'type' => 'idType'));
     *    // FACET idCategory AS categories, year, idType AS type
     *
     * @param array|string $columns Array or multiple string arguments containing column names
     *
     * @return Facet The current object
     * @throws SphinxQLException In case no column in facet
     */
    public function facet($columns = null)
    {
        if (is_array($columns)) {
            foreach ($columns as $key => $column) {
                if (is_int($key)) {
                    $this->facet[] = $column;
                } elseif (is_string($key)) {
                    $asFacet = $this->getConnection()->quoteIdentifier($column) . ' AS ' . $key . ' ';
                    $this->facet[] = new Expression($asFacet);
                }
            }
        } else {
            throw new SphinxQLException('There is no column in facet.');
        }

        return $this;
    }

    /**
     * Facet a function
     *
     * Gets the function passed as $facet->facetFunction('FUNCTION', array('param1', 'param2', ...))
     *
     * Examples:
     * ->facetFunction('category');
     *
     * @param string       $function Function name
     * @param array|string $params   Array or multiple string arguments containing column names
     *
     * @return Facet The current object
     */
    public function facetFunction($function, $params = null)
    {
        $facetFunc = $function . '(';
        if (is_array($params)) {
            $facetFunc .= implode(',', $params);
        }
        $facetFunc .= ')';
        $this->facet[] = new Expression($facetFunc);

        return $this;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param string $column A column to group by
     *
     * @return SphinxQL The current object
     */
    public function By($column)
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
     * @return SphinxQL The current object
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
     * ->facetFunction('category');
     *
     * @param string       $function  Function name
     * @param array        $params    Array  string arguments containing column names
     * @param string       $direction The ordering direction (asc/desc)
     *
     * @return Facet The current object
     */
    public function orderByFunction($function, $params = null, $direction)
    {
        $orderFunc = $function . '(';
        if (is_array($params)) {
            $orderFunc .= implode(',', $params);
        } elseif(is_string($params)) {
            $orderFunc .= $params;
        }
        $orderFunc .= ')';
        $this->facet[] = array('column' => new Expression($orderFunc), 'direction' => $direction);

        return $this;
    }

    /**
     * LIMIT clause
     * Supports also LIMIT offset, limit
     *
     * @param int      $offset Offset if $limit is specified, else limit
     * @param null|int $limit  The limit to set, null for no limit
     *
     * @return SphinxQL The current object
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
     * @return SphinxQL The current object
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;

        return $this;
    }

    /**
     * Compiles the statements for FACET
     *
     * @return facet The current object
     * @throws SphinxQLException In case no column in facet
     */
    public function compileFacet()
    {
        $query = 'FACET ';

        if ( ! empty($this->facet)) {
            $query .= implode(', ', $this->getConnection()->quoteIdentifierArr($this->facet)).' ';
        } else {
            throw new SphinxQLException('There is no column in facet.');
        }

        if ( ! empty($this->by)) {
            $query .= 'BY '. $this->getConnection()->quoteIdentifier($this->by) . ' ';
        }

        if ( ! empty($this->order_by)) {
            $query .= 'ORDER BY ';

            $order_arr = array();

            foreach ($this->order_by as $order) {
                $order_sub = $this->getConnection()->quoteIdentifier($order['column']).' ';

                if ($order['direction'] !== null) {
                    $order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
                }

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
     * @throws SphinxQLException
     */
    public function getFacet()
    {
        return $this->compileFacet()->query;
    }

}