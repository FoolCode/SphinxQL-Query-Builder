<?php

namespace Foolz\SphinxQL;
use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Drivers\MultiResultSetInterface;
use Foolz\SphinxQL\Drivers\ResultSetInterface;

/**
 * Query Builder class for SphinxQL statements.
 * @package Foolz\SphinxQL
 */
class SphinxQL
{
    /**
     * A non-static connection for the current SphinxQL object
     *
     * @var ConnectionInterface
     */
    protected $connection = null;

    /**
     * The last result object.
     *
     * @var array
     */
    protected $last_result = null;

    /**
     * The last compiled query.
     *
     * @var string
     */
    protected $last_compiled = null;

    /**
     * The last chosen method (select, insert, replace, update, delete).
     *
     * @var string
     */
    protected $type = null;

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
    protected $select = array();

    /**
     * From in SphinxQL is the list of indexes that will be used
     *
     * @var array
     */
    protected $from = array();

    /**
     * The list of where and parenthesis, must be inserted in order
     *
     * @var array
     */
    protected $where = array();

    /**
     * The list of matches for the MATCH function in SphinxQL
     *
     * @var array
     */
    protected $match = array();

    /**
     * GROUP BY array to be comma separated
     *
     * @var array
     */
    protected $group_by = array();

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected $within_group_order_by = array();

    /**
     * The list of where and parenthesis, must be inserted in order
     *
     * @var array
     */
    protected $having = array();

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

    /**
     * Value of INTO query for INSERT or REPLACE
     *
     * @var null|string
     */
    protected $into = null;

    /**
     * Array of columns for INSERT or REPLACE
     *
     * @var array
     */
    protected $columns = array();

    /**
     * Array OF ARRAYS of values for INSERT or REPLACE
     *
     * @var array
     */
    protected $values = array();

    /**
     * Array arrays containing column and value for SET in UPDATE
     *
     * @var array
     */
    protected $set = array();

    /**
     * Array of OPTION specific to SphinxQL
     *
     * @var array
     */
    protected $options = array();

    /**
     * Array of FACETs
     *
     * @var Facet[]
     */
    protected $facets = array();

    /**
     * The reference to the object that queued itself and created this object
     *
     * @var null|SphinxQL
     */
    protected $queue_prev = null;

    /**
     * An array of escaped characters for escapeMatch()
     * @var array
     */
    protected $escape_full_chars = array(
        '\\' => '\\\\',
        '(' => '\(',
        ')' => '\)',
        '|' => '\|',
        '-' => '\-',
        '!' => '\!',
        '@' => '\@',
        '~' => '\~',
        '"' => '\"',
        '&' => '\&',
        '/' => '\/',
        '^' => '\^',
        '$' => '\$',
        '=' => '\=',
        '<' => '\<',
    );

    /**
     * An array of escaped characters for fullEscapeMatch()
     * @var array
     */
    protected $escape_half_chars = array(
        '\\' => '\\\\',
        '(' => '\(',
        ')' => '\)',
        '!' => '\!',
        '@' => '\@',
        '~' => '\~',
        '&' => '\&',
        '/' => '\/',
        '^' => '\^',
        '$' => '\$',
        '=' => '\=',
        '<' => '\<',
    );

    public function __construct(ConnectionInterface $connection = null, $static = false)
    {
        $this->connection = $connection;
    }

    /**
     * Creates and setups a SphinxQL object
     *
     * @param ConnectionInterface $connection
     *
     * @return SphinxQL
     */
    public static function create(ConnectionInterface $connection)
    {
        return new static($connection);
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
     * Avoids having the expressions escaped
     *
     * Examples:
     *    $query->where('time', '>', SphinxQL::expr('CURRENT_TIMESTAMP'));
     *    // WHERE time > CURRENT_TIMESTAMP
     *
     * @param string $string The string to keep unaltered
     *
     * @return Expression The new Expression
     */
    public static function expr($string = '')
    {
        return new Expression($string);
    }

    /**
     * Runs the query built
     *
     * @return ResultSetInterface The result of the query
     */
    public function execute()
    {
        // pass the object so execute compiles it by itself
        return $this->last_result = $this->getConnection()->query($this->compile()->getCompiled());
    }

    /**
     * Executes a batch of queued queries
     *
     * @return MultiResultSetInterface The array of results
     * @throws SphinxQLException In case no query is in queue
     */
    public function executeBatch()
    {
        if (count($this->getQueue()) == 0) {
            throw new SphinxQLException('There is no Queue present to execute.');
        }

        $queue = array();

        foreach ($this->getQueue() as $query) {
            $queue[] = $query->compile()->getCompiled();
        }

        return $this->last_result = $this->getConnection()->multiQuery($queue);
    }

    /**
     * Enqueues the current object and returns a new one or the supplied one
     *
     * @param SphinxQL|null $next
     *
     * @return SphinxQL A new SphinxQL object with the current object referenced
     */
    public function enqueue(SphinxQL $next = null)
    {
        if ($next === null) {
            $next = new static($this->getConnection());
        }

        $next->setQueuePrev($this);

        return $next;
    }

    /**
     * Returns the ordered array of enqueued objects
     *
     * @return SphinxQL[] The ordered array of enqueued objects
     */
    public function getQueue()
    {
        $queue = array();
        $curr = $this;

        do {
            if ($curr->type != null) {
                $queue[] = $curr;
            }
        } while ($curr = $curr->getQueuePrev());

        return array_reverse($queue);
    }

    /**
     * Gets the enqueued object
     *
     * @return SphinxQL|null
     */
    public function getQueuePrev()
    {
        return $this->queue_prev;
    }

    /**
     * Sets the reference to the enqueued object
     *
     * @param SphinxQL $query The object to set as previous
     *
     * @return SphinxQL
     */
    public function setQueuePrev($query)
    {
        $this->queue_prev = $query;

        return $this;
    }

    /**
     * Returns the result of the last query
     *
     * @return array The result of the last query
     */
    public function getResult()
    {
        return $this->last_result;
    }

    /**
     * Returns the latest compiled query
     *
     * @return string The last compiled query
     */
    public function getCompiled()
    {
        return $this->last_compiled;
    }

    /**
     * Begins transaction
     */
    public function transactionBegin()
    {
        $this->getConnection()->query('BEGIN');
    }

    /**
     * Commits transaction
     */
    public function transactionCommit()
    {
        $this->getConnection()->query('COMMIT');
    }

    /**
     * Rollbacks transaction
     */
    public function transactionRollback()
    {
        $this->getConnection()->query('ROLLBACK');
    }

    /**
     * Runs the compile function
     *
     * @return SphinxQL
     */
    public function compile()
    {
        switch ($this->type) {
            case 'select':
                $this->compileSelect();
                break;
            case 'insert':
            case 'replace':
                $this->compileInsert();
                break;
            case 'update':
                $this->compileUpdate();
                break;
            case 'delete':
                $this->compileDelete();
                break;
            case 'query':
                $this->compileQuery();
                break;
        }

        return $this;
    }

    public function compileQuery()
    {
        $this->last_compiled = $this->query;

        return $this;
    }

    /**
     * Compiles the MATCH part of the queries
     * Used by: SELECT, DELETE, UPDATE
     *
     * @return string The compiled MATCH
     */
    public function compileMatch()
    {
        $query = '';

        if (!empty($this->match)) {
            $query .= 'WHERE MATCH(';

            $matched = array();

            foreach ($this->match as $match) {
                $pre = '';
                if ($match['column'] instanceof \Closure) {
                    $sub = new Match($this);
                    call_user_func($match['column'], $sub);
                    $pre .= $sub->compile()->getCompiled();
                } elseif ($match['column'] instanceof Match) {
                    $pre .= $match['column']->compile()->getCompiled();
                } elseif (empty($match['column'])) {
                    $pre .= '';
                } elseif (is_array($match['column'])) {
                    $pre .= '@('.implode(',', $match['column']).') ';
                } else {
                    $pre .= '@'.$match['column'].' ';
                }

                if ($match['half']) {
                    $pre .= $this->halfEscapeMatch($match['value']);
                } else {
                    $pre .= $this->escapeMatch($match['value']);
                }

                $matched[] = '('.$pre.')';
            }

            $matched = implode(' ', $matched);
            $query .= $this->getConnection()->escape(trim($matched)).') ';
        }
        return $query;
    }

    /**
     * Compiles the WHERE part of the queries
     * It interacts with the MATCH() and of course isn't usable stand-alone
     * Used by: SELECT, DELETE, UPDATE
     *
     * @return string The compiled WHERE
     */
    public function compileWhere()
    {
        $query = '';

        if (empty($this->match) && !empty($this->where)) {
            $query .= 'WHERE ';
        }

        if (!empty($this->where)) {
            foreach ($this->where as $key => $where) {
                if ($key > 0 || !empty($this->match)) {
                    $query .= 'AND ';
                }
                $query .= $this->compileFilterCondition($where);
            }
        }

        return $query;
    }

    public function compileFilterCondition($filter)
    {
        $query = '';

        if (!empty($filter)) {
            if (strtoupper($filter['operator']) === 'BETWEEN') {
                $query .= $this->getConnection()->quoteIdentifier($filter['column']);
                $query .= ' BETWEEN ';
                $query .= $this->getConnection()->quote($filter['value'][0]).' AND '
                    .$this->getConnection()->quote($filter['value'][1]).' ';
            } else {
                // id can't be quoted!
                if ($filter['column'] === 'id') {
                    $query .= 'id ';
                } else {
                    $query .= $this->getConnection()->quoteIdentifier($filter['column']).' ';
                }

                if (in_array(strtoupper($filter['operator']), array('IN', 'NOT IN'), true)) {
                    $query .= strtoupper($filter['operator']).' ('.implode(', ', $this->getConnection()->quoteArr($filter['value'])).') ';
                } else {
                    $query .= $filter['operator'].' '.$this->getConnection()->quote($filter['value']).' ';
                }
            }
        }

        return $query;
    }

    /**
     * Compiles the statements for SELECT
     *
     * @return SphinxQL
     */
    public function compileSelect()
    {
        $query = '';

        if ($this->type == 'select') {
            $query .= 'SELECT ';

            if (!empty($this->select)) {
                $query .= implode(', ', $this->getConnection()->quoteIdentifierArr($this->select)).' ';
            } else {
                $query .= '* ';
            }
        }

        if (!empty($this->from)) {
            if ($this->from instanceof \Closure) {
                $sub = new static($this->getConnection());
                call_user_func($this->from, $sub);
                $query .= 'FROM ('.$sub->compile()->getCompiled().') ';
            } elseif ($this->from instanceof SphinxQL) {
                $query .= 'FROM ('.$this->from->compile()->getCompiled().') ';
            } else {
                $query .= 'FROM '.implode(', ', $this->getConnection()->quoteIdentifierArr($this->from)).' ';
            }
        }

        $query .= $this->compileMatch().$this->compileWhere();

        if (!empty($this->group_by)) {
            $query .= 'GROUP BY '.implode(', ', $this->getConnection()->quoteIdentifierArr($this->group_by)).' ';
        }

        if (!empty($this->within_group_order_by)) {
            $query .= 'WITHIN GROUP ORDER BY ';

            $order_arr = array();

            foreach ($this->within_group_order_by as $order) {
                $order_sub = $this->getConnection()->quoteIdentifier($order['column']).' ';

                if ($order['direction'] !== null) {
                    $order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
                }

                $order_arr[] = $order_sub;
            }

            $query .= implode(', ', $order_arr).' ';
        }

        if (!empty($this->having)) {
            $query .= 'HAVING '.$this->compileFilterCondition($this->having);
        }

        if (!empty($this->order_by)) {
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

        if (!empty($this->options)) {
            $options = array();

            foreach ($this->options as $option) {
                if ($option['value'] instanceof Expression) {
                    $option['value'] = $option['value']->value();
                } elseif (is_array($option['value'])) {
                    array_walk(
                        $option['value'],
                        function (&$val, $key) {
                            $val = $key.'='.$val;
                        }
                    );
                    $option['value'] = '('.implode(', ', $option['value']).')';
                } else {
                    $option['value'] = $this->getConnection()->quote($option['value']);
                }

                $options[] = $this->getConnection()->quoteIdentifier($option['name'])
                    .' = '.$option['value'];
            }

            $query .= 'OPTION '.implode(', ', $options).' ';
        }

        if (!empty($this->facets)) {
            $facets = array();

            foreach ($this->facets as $facet) {
                // dynamically set the own SphinxQL connection if the Facet doesn't own one
                if ($facet->getConnection() === null) {
                    $facet->setConnection($this->getConnection());
                    $facets[] = $facet->getFacet();
                    // go back to the status quo for reuse
                    $facet->setConnection();
                } else {
                    $facets[] = $facet->getFacet();
                }
            }

            $query .= implode(' ', $facets);
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for INSERT or REPLACE
     *
     * @return SphinxQL
     */
    public function compileInsert()
    {
        if ($this->type == 'insert') {
            $query = 'INSERT ';
        } else {
            $query = 'REPLACE ';
        }

        if ($this->into !== null) {
            $query .= 'INTO '.$this->into.' ';
        }

        if (!empty($this->columns)) {
            $query .= '('.implode(', ', $this->getConnection()->quoteIdentifierArr($this->columns)).') ';
        }

        if (!empty($this->values)) {
            $query .= 'VALUES ';
            $query_sub = '';

            foreach ($this->values as $value) {
                $query_sub[] = '('.implode(', ', $this->getConnection()->quoteArr($value)).')';
            }

            $query .= implode(', ', $query_sub);
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for UPDATE
     *
     * @return SphinxQL
     */
    public function compileUpdate()
    {
        $query = 'UPDATE ';

        if ($this->into !== null) {
            $query .= $this->into.' ';
        }

        if (!empty($this->set)) {
            $query .= 'SET ';

            $query_sub = array();

            foreach ($this->set as $column => $value) {
                // MVA support
                if (is_array($value)) {
                    $query_sub[] = $this->getConnection()->quoteIdentifier($column)
                        .' = ('.implode(', ', $this->getConnection()->quoteArr($value)).')';
                } else {
                    $query_sub[] = $this->getConnection()->quoteIdentifier($column)
                        .' = '.$this->getConnection()->quote($value);
                }
            }

            $query .= implode(', ', $query_sub).' ';
        }

        $query .= $this->compileMatch().$this->compileWhere();

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for DELETE
     *
     * @return SphinxQL
     */
    public function compileDelete()
    {
        $query = 'DELETE ';

        if (!empty($this->from)) {
            $query .= 'FROM '.$this->from[0].' ';
        }

        if (!empty($this->where)) {
            $query .= $this->compileWhere();
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Sets a query to be executed
     *
     * @param string $sql A SphinxQL query to execute
     *
     * @return SphinxQL
     */
    public function query($sql)
    {
        $this->type = 'query';
        $this->query = $sql;

        return $this;
    }

    /**
     * Select the columns
     *
     * Gets the arguments passed as $sphinxql->select('one', 'two')
     * Using it without arguments equals to having '*' as argument
     * Using it with array maps values as column names
     *
     * Examples:
     *    $query->select('title');
     *    // SELECT title
     *
     *    $query->select('title', 'author', 'date');
     *    // SELECT title, author, date
     *
     *    $query->select(['id', 'title']);
     *    // SELECT id, title
     *
     * @param array|string $columns Array or multiple string arguments containing column names
     *
     * @return SphinxQL
     */
    public function select($columns = null)
    {
        $this->reset();
        $this->type = 'select';

        if (is_array($columns)) {
            $this->select = $columns;
        } else {
            $this->select = \func_get_args();
        }

        return $this;
    }

    /**
     * Activates the INSERT mode
     *
     * @return SphinxQL
     */
    public function insert()
    {
        $this->reset();
        $this->type = 'insert';

        return $this;
    }

    /**
     * Activates the REPLACE mode
     *
     * @return SphinxQL
     */
    public function replace()
    {
        $this->reset();
        $this->type = 'replace';

        return $this;
    }

    /**
     * Activates the UPDATE mode
     *
     * @param string $index The index to update into
     *
     * @return SphinxQL
     */
    public function update($index)
    {
        $this->reset();
        $this->type = 'update';
        $this->into($index);

        return $this;
    }

    /**
     * Activates the DELETE mode
     *
     * @return SphinxQL
     */
    public function delete()
    {
        $this->reset();
        $this->type = 'delete';

        return $this;
    }

    /**
     * FROM clause (Sphinx-specific since it works with multiple indexes)
     * func_get_args()-enabled
     *
     * @param array $array An array of indexes to use
     *
     * @return SphinxQL
     */
    public function from($array = null)
    {
        if (is_string($array)) {
            $this->from = \func_get_args();
        }

        if (is_array($array) || $array instanceof \Closure || $array instanceof SphinxQL) {
            $this->from = $array;
        }

        return $this;
    }

    /**
     * MATCH clause (Sphinx-specific)
     *
     * @param mixed    $column The column name (can be array, string, Closure, or Match)
     * @param string   $value  The value
     * @param boolean  $half  Exclude ", |, - control characters from being escaped
     *
     * @return SphinxQL
     */
    public function match($column, $value = null, $half = false)
    {
        if ($column === '*' || (is_array($column) && in_array('*', $column))) {
            $column = array();
        }

        $this->match[] = array('column' => $column, 'value' => $value, 'half' => $half);

        return $this;
    }

    /**
     * WHERE clause
     *
     * Examples:
     *    $query->where('column', 'value');
     *    // WHERE column = 'value'
     *
     *    $query->where('column', '=', 'value');
     *    // WHERE column = 'value'
     *
     *    $query->where('column', '>=', 'value')
     *    // WHERE column >= 'value'
     *
     *    $query->where('column', 'IN', array('value1', 'value2', 'value3'));
     *    // WHERE column IN ('value1', 'value2', 'value3')
     *
     *    $query->where('column', 'BETWEEN', array('value1', 'value2'))
     *    // WHERE column BETWEEN 'value1' AND 'value2'
     *    // WHERE example BETWEEN 10 AND 100
     *
     * @param string   $column   The column name
     * @param string   $operator The operator to use
     * @param string   $value    The value to check against
     *
     * @return SphinxQL
     */
    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = array(
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        );

        return $this;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param string $column A column to group by
     *
     * @return SphinxQL
     */
    public function groupBy($column)
    {
        $this->group_by[] = $column;

        return $this;
    }

    /**
     * WITHIN GROUP ORDER BY clause (SphinxQL-specific)
     * Adds to the previously added columns
     * Works just like a classic ORDER BY
     *
     * @param string $column    The column to group by
     * @param string $direction The group by direction (asc/desc)
     *
     * @return SphinxQL
     */
    public function withinGroupOrderBy($column, $direction = null)
    {
        $this->within_group_order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * HAVING clause
     *
     * Examples:
     *    $sq->having('column', 'value');
     *    // HAVING column = 'value'
     *
     *    $sq->having('column', '=', 'value');
     *    // HAVING column = 'value'
     *
     *    $sq->having('column', '>=', 'value')
     *    // HAVING column >= 'value'
     *
     *    $sq->having('column', 'IN', array('value1', 'value2', 'value3'));
     *    // HAVING column IN ('value1', 'value2', 'value3')
     *
     *    $sq->having('column', 'BETWEEN', array('value1', 'value2'))
     *    // HAVING column BETWEEN 'value1' AND 'value2'
     *    // HAVING example BETWEEN 10 AND 100
     *
     * @param string   $column   The column name
     * @param string   $operator The operator to use
     * @param string   $value    The value to check against
     *
     * @return SphinxQL The current object
     */
    public function having($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->having = array(
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        );

        return $this;
    }

    /**
     * ORDER BY clause
     * Adds to the previously added columns
     *
     * @param string $column    The column to order on
     * @param string $direction The ordering direction (asc/desc)
     *
     * @return SphinxQL
     */
    public function orderBy($column, $direction = null)
    {
        $this->order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * LIMIT clause
     * Supports also LIMIT offset, limit
     *
     * @param int      $offset Offset if $limit is specified, else limit
     * @param null|int $limit  The limit to set, null for no limit
     *
     * @return SphinxQL
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
     * @return SphinxQL
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;

        return $this;
    }

    /**
     * OPTION clause (SphinxQL-specific)
     * Used by: SELECT
     *
     * @param string $name  Option name
     * @param string $value Option value
     *
     * @return SphinxQL
     */
    public function option($name, $value)
    {
        $this->options[] = array('name' => $name, 'value' => $value);

        return $this;
    }

    /**
     * INTO clause
     * Used by: INSERT, REPLACE
     *
     * @param string $index The index to insert/replace into
     *
     * @return SphinxQL
     */
    public function into($index)
    {
        $this->into = $index;

        return $this;
    }

    /**
     * Set columns
     * Used in: INSERT, REPLACE
     * func_get_args()-enabled
     *
     * @param array $array The array of columns
     *
     * @return SphinxQL
     */
    public function columns($array = array())
    {
        if (is_array($array)) {
            $this->columns = $array;
        } else {
            $this->columns = \func_get_args();
        }

        return $this;
    }

    /**
     * Set VALUES
     * Used in: INSERT, REPLACE
     * func_get_args()-enabled
     *
     * @param array $array The array of values matching the columns from $this->columns()
     *
     * @return SphinxQL
     */
    public function values($array)
    {
        if (is_array($array)) {
            $this->values[] = $array;
        } else {
            $this->values[] = \func_get_args();
        }

        return $this;
    }

    /**
     * Set column and relative value
     * Used in: INSERT, REPLACE
     *
     * @param string $column The column name
     * @param string $value  The value
     *
     * @return SphinxQL
     */
    public function value($column, $value)
    {
        if ($this->type === 'insert' || $this->type === 'replace') {
            $this->columns[] = $column;
            $this->values[0][] = $value;
        } else {
            $this->set[$column] = $value;
        }

        return $this;
    }

    /**
     * Allows passing an array with the key as column and value as value
     * Used in: INSERT, REPLACE, UPDATE
     *
     * @param array $array Array of key-values
     *
     * @return SphinxQL
     */
    public function set($array)
    {
        foreach ($array as $key => $item) {
            $this->value($key, $item);
        }

        return $this;
    }

    /**
     * Allows passing an array with the key as column and value as value
     * Used in: INSERT, REPLACE, UPDATE
     *
     * @param Facet $facet
     * @return SphinxQL
     */
    public function facet($facet)
    {
        $this->facets[] = $facet;

        return $this;
    }

    /**
     * Sets the characters used for escapeMatch().
     *
     * @param array $array The array of characters to escape
     *
     * @return SphinxQL The escaped characters
     */
    public function setFullEscapeChars($array = array())
    {
        if (!empty($array)) {
            $this->escape_full_chars = $this->compileEscapeChars($array);
        }

        return $this;
    }

    /**
     * Sets the characters used for halfEscapeMatch().
     *
     * @param array $array The array of characters to escape
     *
     * @return SphinxQL The escaped characters
     */
    public function setHalfEscapeChars($array = array())
    {
        if (!empty($array)) {
            $this->escape_half_chars = $this->compileEscapeChars($array);
        }

        return $this;
    }

    /**
     * Compiles an array containing the characters and escaped characters into a key/value configuration.
     *
     * @param array $array The array of characters to escape
     *
     * @return array An array of the characters and it's escaped counterpart
     */
    public function compileEscapeChars($array = array())
    {
        $result = array();
        foreach ($array as $character) {
            $result[$character] = '\\'.$character;
        }

        return $result;
    }

    /**
     * Escapes the query for the MATCH() function
     *
     * @param string $string The string to escape for the MATCH
     *
     * @return string The escaped string
     */
    public function escapeMatch($string)
    {
        if ($string instanceof Expression) {
            return $string->value();
        }

        return mb_strtolower(str_replace(array_keys($this->escape_full_chars), array_values($this->escape_full_chars), $string), 'utf8');
    }

    /**
     * Escapes the query for the MATCH() function
     * Allows some of the control characters to pass through for use with a search field: -, |, "
     * It also does some tricks to wrap/unwrap within " the string and prevents errors
     *
     * @param string $string The string to escape for the MATCH
     *
     * @return string The escaped string
     */
    public function halfEscapeMatch($string)
    {
        if ($string instanceof Expression) {
            return $string->value();
        }

        $string = str_replace(array_keys($this->escape_half_chars), array_values($this->escape_half_chars), $string);

        // this manages to lower the error rate by a lot
        if (mb_substr_count($string, '"', 'utf8') % 2 !== 0) {
            $string .= '"';
        }

        $string = preg_replace('/-[\s-]*-/u', '-', $string);

        $from_to_preg = array(
            '/([-|])\s*$/u'        => '\\\\\1',
            '/\|[\s|]*\|/u'        => '|',
            '/(\S+)-(\S+)/u'       => '\1\-\2',
            '/(\S+)\s+-\s+(\S+)/u' => '\1 \- \2',
        );

        $string = mb_strtolower(preg_replace(array_keys($from_to_preg), array_values($from_to_preg), $string), 'utf8');

        return $string;
    }

    /**
     * Clears the existing query build for new query when using the same SphinxQL instance.
     *
     * @return SphinxQL
     */
    public function reset()
    {
        $this->query = null;
        $this->select = array();
        $this->from = array();
        $this->where = array();
        $this->match = array();
        $this->group_by = array();
        $this->within_group_order_by = array();
        $this->having = array();
        $this->order_by = array();
        $this->offset = null;
        $this->limit = null;
        $this->into = null;
        $this->columns = array();
        $this->values = array();
        $this->set = array();
        $this->options = array();

        return $this;
    }

    public function resetWhere()
    {
        $this->where = array();

        return $this;
    }

    public function resetMatch()
    {
        $this->match = array();

        return $this;
    }

    public function resetGroupBy()
    {
        $this->group_by = array();

        return $this;
    }

    public function resetWithinGroupOrderBy()
    {
        $this->within_group_order_by = array();

        return $this;
    }

    public function resetHaving()
    {
        $this->having = array();

        return $this;
    }

    public function resetOrderBy()
    {
        $this->order_by = array();

        return $this;
    }

    public function resetOptions()
    {
        $this->options = array();

        return $this;
    }
}
