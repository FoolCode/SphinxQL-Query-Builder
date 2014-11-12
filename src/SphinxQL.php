<?php

namespace Foolz\SphinxQL;

/**
 * Query Builder class for SphinxQL statements.
 * @package Foolz\SphinxQL
 */
class SphinxQL
{
    /**
     * The connection for all SphinxQL objects
     *
     * @var \Foolz\SphinxQL\Connection
     * @deprecated
     */
    protected static $stored_connection = null;

    /**
     * A non-static connection for the current SphinxQL object
     *
     * @var \Foolz\SphinxQL\Connection
     */
    protected $local_connection = null;

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
     * The reference to the object that queued itself and created this object
     *
     * @var null|\Foolz\SphinxQL\SphinxQL
     */
    protected $queue_prev = null;

    /**
     * Ready for use queries
     *
     * @var array
     */
    protected static $show_queries = array(
        'meta' => 'SHOW META',
        'warnings' => 'SHOW WARNINGS',
        'status' => 'SHOW STATUS',
        'tables' => 'SHOW TABLES',
        'variables' => 'SHOW VARIABLES',
        'variablesSession' => 'SHOW SESSION VARIABLES',
        'variablesGlobal' => 'SHOW GLOBAL VARIABLES',
    );

    public function __construct(ConnectionInterface $connection = null, $static = false)
    {
        if ($static) {
            static::$stored_connection = $connection;
        } else {
            $this->local_connection = $connection;
        }
    }

    /**
     * Forges a SphinxQL object with a Connection shared among all SphinxQL objects
     *
     * @param null|Connection $connection
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     * @deprecated Use ::create instead, coupled with an own static method if static connection is necessary
     */
    public static function forge(ConnectionInterface $connection = null)
    {
        return new SphinxQL($connection, true);
    }

    /**
     * Creates and setups a SphinxQL object
     *
     * @param Connection $connection
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public static function create(ConnectionInterface $connection)
    {
        return new SphinxQL($connection);
    }

    /**
     * Returns the currently attached connection
     *
     * @returns \Foolz\SphinxQL\Connection
     */
    public function getConnection()
    {
        if ($this->local_connection) {
            return $this->local_connection;
        }

        return static::$stored_connection;
    }

    /**
     * Used for the SHOW queries
     *
     * @param string $method      The method
     * @param array  $parameters  The parameters
     *
     * @return array The result of the SHOW query
     * @throws \BadMethodCallException If there's no such a method
     */
    public function __call($method, $parameters)
    {
        if (isset(static::$show_queries[$method])) {
            $ordered = array();
            $result = $this->getConnection()->query(static::$show_queries[$method]);

            if ($method === 'tables') {
                return $result;
            }

            foreach ($result as $item) {
                $ordered[$item['Variable_name']] = $item['Value'];
            }

            return $ordered;
        }

        throw new \BadMethodCallException($method);
    }

    /**
     * Avoids having the expressions escaped
     *
     * Example
     *    $sq->where('time', '>', SphinxQL::expr('CURRENT_TIMESTAMP'));
     *    // WHERE `time` > CURRENT_TIMESTAMP
     *
     * @param string $string The string to keep unaltered
     *
     * @return \Foolz\SphinxQL\Expression The new Expression
     */
    public static function expr($string = '')
    {
        return new Expression($string);
    }

    /**
     * Runs the query built
     *
     * @return array The result of the query
     */
    public function execute()
    {
        // pass the object so execute compiles it by itself
        return $this->last_result = $this->getConnection()->query($this->compile()->getCompiled());
    }

    /**
     * Executes a batch of queued queries
     *
     * @return array The array of results
     * @throws SphinxQLException In case no query is in queue
     */
    public function executeBatch()
    {
        if (count($this->getQueue()) == 0) {
            throw new SphinxQLException('There is no Queue present to execute.');
        }

        $queue = array();

        foreach ($this->getQueue() as $sq) {
            $queue[] = $sq->compile()->getCompiled();
        }

        return $this->last_result = $this->getConnection()->multiQuery($queue);
    }

    /**
     * Enqueues the current object and returns a new one or the supplied one
     *
     * @param SphinxQL|null $next
     *
     * @return \Foolz\SphinxQL\SphinxQL A new SphinxQL object with the current object referenced
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
     * @return \Foolz\SphinxQL\SphinxQL[] The ordered array of enqueued objects
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
     * @param $sq SphinxQL The object to set as previous
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function setQueuePrev($sq)
    {
        $this->queue_prev = $sq;

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
     * SET syntax
     *
     * @param string  $name   The name of the variable
     * @param mixed   $value  The value o the variable
     * @param boolean $global True if the variable should be global, false otherwise
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->setVariable(...)->execute();
     */
    public function setVariable($name, $value, $global = false)
    {
        $query = 'SET ';

        if ($global) {
            $query .= 'GLOBAL ';
        }

        $user_var = strpos($name, '@') === 0;

        // if it has an @ it's a user variable and we can't wrap it
        if ($user_var) {
            $query .= $name.' ';
        } else {
            $query .= $this->getConnection()->quoteIdentifier($name).' ';
        }

        // user variables must always be processed as arrays
        if ($user_var && ! is_array($value)) {
            $query .= '= ('.$this->getConnection()->quote($value).')';
        } elseif (is_array($value)) {
            $query .= '= ('.implode(', ', $this->getConnection()->quoteArr($value)).')';
        } else {
            $query .= '= '.$this->getConnection()->quote($value);
        }

        $this->getConnection()->query($query);
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
     * CALL SNIPPETS syntax
     *
     * @param string $data
     * @param string $index
     * @param array  $extra
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->callSnippets(...)->execute();
     */
    public function callSnippets($data, $index, $extra = array())
    {
        array_unshift($extra, $index);
        array_unshift($extra, $data);

        return $this->getConnection()->query('CALL SNIPPETS('.implode(', ', $this->getConnection()->quoteArr($extra)).')');
    }

    /**
     * CALL KEYWORDS syntax
     *
     * @param string      $text
     * @param string      $index
     * @param null|string $hits
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->callKeywords(...)->execute();
     */
    public function callKeywords($text, $index, $hits = null)
    {
        $arr = array($text, $index);
        if ($hits !== null) {
            $arr[] = $hits;
        }

        return $this->getConnection()->query('CALL KEYWORDS('.implode(', ', $this->getConnection()->quoteArr($arr)).')');
    }

    /**
     * DESCRIBE syntax
     *
     * @param string $index The name of the index
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->describe(...)->execute();
     */
    public function describe($index)
    {
        return $this->getConnection()->query('DESCRIBE '.$this->getConnection()->quoteIdentifier($index));
    }

    /**
     * CREATE FUNCTION syntax
     *
     * @param string $udf_name
     * @param string $returns  Whether INT|BIGINT|FLOAT
     * @param string $so_name
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->createFunction(...)->execute();
     */
    public function createFunction($udf_name, $returns, $so_name)
    {
        return $this->getConnection()->query('CREATE FUNCTION '.$this->getConnection()->quoteIdentifier($udf_name).
            ' RETURNS '.$returns.' SONAME '.$this->getConnection()->quote($so_name));
    }

    /**
     * DROP FUNCTION syntax
     *
     * @param string $udf_name
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->dropFunction(...)->execute();
     */
    public function dropFunction($udf_name)
    {
        return $this->getConnection()->query('DROP FUNCTION '.$this->getConnection()->quoteIdentifier($udf_name));
    }

    /**
     * ATTACH INDEX * TO RTINDEX * syntax
     *
     * @param string $disk_index
     * @param string $rt_index
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->attachIndex(...)->execute();
     */
    public function attachIndex($disk_index, $rt_index)
    {
        return $this->getConnection()->query('ATTACH INDEX '.$this->getConnection()->quoteIdentifier($disk_index).
            ' TO RTINDEX '. $this->getConnection()->quoteIdentifier($rt_index));
    }

    /**
     * FLUSH RTINDEX syntax
     *
     * @param string $index
     *
     * @return array The result of the query
     * @deprecated Use Helper::create($conn)->flushRtIndex(...)->execute();
     */
    public function flushRtIndex($index)
    {
        return $this->getConnection()->query('FLUSH RTINDEX '.$this->getConnection()->quoteIdentifier($index));
    }

    /**
     * Runs the compile function
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
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

        if ( ! empty($this->match)) {
            $query .= 'WHERE MATCH(';

            $matched = array();

            foreach ($this->match as $match) {
                $pre = '';
                if (empty($match['column'])) {
                    $pre .= '';
                } elseif (is_array($match['column'])) {
                    $pre .= '@('.implode(',',$match['column']).') ';
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

        if (empty($this->match) && ! empty($this->where)) {
            $query .= 'WHERE ';
        }

        if ( ! empty($this->where)) {
            $just_opened = false;

            foreach ($this->where as $key => $where) {
                if (in_array($where['ext_operator'], array('AND (', 'OR (', ')'))) {
                    // if match is not empty we've got to use an operator
                    if ($key == 0 || ! empty($this->match)) {
                        $query .= '(';

                        $just_opened = true;
                    } else {
                        $query .= $where['ext_operator'].' ';
                    }

                    continue;
                }

                if ($key > 0 && ! $just_opened || ! empty($this->match)) {
                    $query .= $where['ext_operator'].' '; // AND/OR
                }

                $just_opened = false;

                if (strtoupper($where['operator']) === 'BETWEEN') {
                    $query .= $this->getConnection()->quoteIdentifier($where['column']);
                    $query .=' BETWEEN ';
                    $query .= $this->getConnection()->quote($where['value'][0]).' AND '
                        .$this->getConnection()->quote($where['value'][1]).' ';
                } else {
                    // id can't be quoted!
                    if ($where['column'] === 'id') {
                        $query .= 'id ';
                    } else {
                        $query .= $this->getConnection()->quoteIdentifier($where['column']).' ';
                    }

                    if (in_array(strtoupper($where['operator']), array('IN', 'NOT IN'), true)) {
                        $query .= strtoupper($where['operator']).' ('.implode(', ', $this->getConnection()->quoteArr($where['value'])).') ';
                    } else {
                        $query .= $where['operator'].' '.$this->getConnection()->quote($where['value']).' ';
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Compiles the statements for SELECT
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function compileSelect()
    {
        $query = '';

        if ($this->type == 'select') {
            $query .= 'SELECT ';

            if ( ! empty($this->select)) {
                $query .= implode(', ', $this->getConnection()->quoteIdentifierArr($this->select)).' ';
            } else {
                $query .= '* ';
            }
        }

        if ( ! empty($this->from)) {
            $query .= 'FROM '.implode(', ', $this->getConnection()->quoteIdentifierArr($this->from)).' ';
        }

        $query .= $this->compileMatch().$this->compileWhere();

        if ( ! empty($this->group_by)) {
            $query .= 'GROUP BY '.implode(', ', $this->getConnection()->quoteIdentifierArr($this->group_by)).' ';
        }

        if ( ! empty($this->within_group_order_by)) {
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

            $query .= 'OPTION '.implode(', ', $options);
        }

        $query = trim($query);
        $this->last_compiled = $query;

        return $this;
    }

    /**
     * Compiles the statements for INSERT or REPLACE
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
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

        if ( ! empty ($this->columns)) {
            $query .= '('.implode(', ', $this->getConnection()->quoteIdentifierArr($this->columns)).') ';
        }

        if ( ! empty ($this->values)) {
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function compileUpdate()
    {
        $query = 'UPDATE ';

        if ($this->into !== null) {
            $query .= $this->into.' ';
        }

        if ( ! empty($this->set)) {
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function compileDelete()
    {
        $query = 'DELETE ';

        if ( ! empty($this->from)) {
            $query .= 'FROM '.$this->from[0].' ';
        }

        if ( ! empty($this->where)) {
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function query($sql)
    {
        $this->type = 'query';
        $this->query = $sql;

        return $this;
    }

    /**
     * Select the columns
     * Gets the arguments passed as $sphinxql->select('one', 'two')
     * Using it without arguments equals to having '*' as argument
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function select()
    {
        $this->reset();
        $this->type = 'select';
        $this->select = \func_get_args();

        return $this;
    }

    /**
     * Activates the INSERT mode
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function from($array = null)
    {
        if (is_string($array)) {
            $this->from = \func_get_args();
        }

        if (is_array($array)) {
            $this->from = $array;
        }

        return $this;
    }

    /**
     * MATCH clause (Sphinx-specific)
     *
     * @param mixed    $column The column name (can be an array or a string)
     * @param string   $value  The value
     * @param boolean  $half  Exclude ", |, - control characters from being escaped
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function match($column, $value, $half = false)
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
     *    $sq->where('column', 'value');
     *    // WHERE `column` = 'value'
     *
     *    $sq->where('column', '=', 'value');
     *    // WHERE `column` = 'value'
     *
     *    $sq->where('column', '>=', 'value')
     *    // WHERE `column` >= 'value'
     *
     *    $sq->where('column', 'IN', array('value1', 'value2', 'value3'));
     *    // WHERE `column` IN ('value1', 'value2', 'value3')
     *
     *    $sq->where('column', 'BETWEEN', array('value1', 'value2'))
     *    // WHERE `column` BETWEEN 'value1' AND 'value2'
     *    // WHERE `example` BETWEEN 10 AND 100
     *
     * @param string   $column   The column name
     * @param string   $operator The operator to use
     * @param string   $value    The value to check against
     * @param boolean  $or      If it should be prepended with OR (true) or AND (false) - not available as for Sphinx 2.0.2
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function where($column, $operator, $value = null, $or = false)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = array(
            'ext_operator' => $or ? 'OR' : 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        );

        return $this;
    }

    /**
     * OR WHERE - at this time (Sphinx 2.0.2) it's not available
     *
     * @param string $column    The column name
     * @param string $operator  The operator to use
     * @param mixed   $value     The value to compare against
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function orWhere($column, $operator, $value = null)
    {
        $this->where($column, $operator, $value, true);

        return $this;
    }

    /**
     * Opens a parenthesis prepended with AND (where necessary)
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function whereOpen()
    {
        $this->where[] = array('ext_operator' => 'AND (');

        return $this;
    }

    /**
     * Opens a parenthesis prepended with OR (where necessary)
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function orWhereOpen()
    {
        $this->where[] = array('ext_operator' => 'OR (');

        return $this;
    }

    /**
     * Closes a parenthesis in WHERE
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function whereClose()
    {
        $this->where[] = array('ext_operator' => ')');

        return $this;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param string $column A column to group by
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function withinGroupOrderBy($column, $direction = null)
    {
        $this->within_group_order_by[] = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * ORDER BY clause
     * Adds to the previously added columns
     *
     * @param string $column    The column to order on
     * @param string $direction The ordering direction (asc/desc)
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
     * @return \Foolz\SphinxQL\SphinxQL The current object
     */
    public function set($array)
    {
        foreach ($array as $key => $item)
        {
            $this->value($key, $item);
        }

        return $this;
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

        $from = array('\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '=');
        $to = array('\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\=');

        return mb_strtolower(str_replace($from, $to, $string));
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

        $from_to = array(
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
        );

        $string = str_replace(array_keys($from_to), array_values($from_to), $string);

        // this manages to lower the error rate by a lot
        if (mb_substr_count($string, '"') % 2 !== 0) {
            $string .= '"';
        }

        $string = preg_replace('/-[\s-]*-/u', '-', $string);

        $from_to_preg = array(
            '/([-|])\s*$/u' => '\\\\\1',
            '/\|[\s|]*\|/u' => '|',

            // prevent accidental negation in natural language
            '/(\S+)-(\S+)/u'       => '\1\-\2',
            '/(\S+)\s+-\s+(\S+)/u' => '\1 \- \2',
        );

        $string = mb_strtolower(preg_replace(array_keys($from_to_preg), array_values($from_to_preg), $string));

        return $string;
    }

    /**
     * Clears the existing query build for new query when using the same SphinxQL instance.
     *
     * @return \Foolz\SphinxQL\SphinxQL The current object
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
        $this->order_by = array();
        $this->offset = null;
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
