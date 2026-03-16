<?php

namespace Foolz\SphinxQL;

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Drivers\MultiResultSetInterface;
use Foolz\SphinxQL\Drivers\ResultSetInterface;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;

/**
 * Query Builder class for SphinxQL statements.
 */
class SphinxQL
{
    /**
     * A non-static connection for the current SphinxQL object
     *
     * @var ConnectionInterface
     */
    protected ?ConnectionInterface $connection;

    /**
     * The last result object.
     *
     * @var array
     */
    protected ResultSetInterface|MultiResultSetInterface|array|int|null $last_result = null;

    /**
     * The last compiled query.
     *
     * @var string
     */
    protected ?string $last_compiled = null;

    /**
     * The last chosen method (select, insert, replace, update, delete).
     *
     * @var string
     */
    protected ?string $type = null;

    /**
     * An SQL query that is not yet executed or "compiled"
     *
     * @var string
     */
    protected ?string $query = null;

    /**
     * Array of select elements that will be comma separated.
     *
     * @var array
     */
    protected array $select = array();

    /**
     * From in SphinxQL is the list of indexes that will be used
     *
     * @var array
     */
    protected array|\Closure|SphinxQL $from = array();

    /**
     * JOIN clauses for SELECT queries
     *
     * @var array
     */
    protected array $joins = array();

    /**
     * WHERE clause token list (conditions and grouping parenthesis)
     *
     * @var array
     */
    protected array $where = array();

    /**
     * The list of matches for the MATCH function in SphinxQL
     *
     * @var array
     */
    protected array $match = array();

    /**
     * GROUP BY array to be comma separated
     *
     * @var array
     */
    protected array $group_by = array();

    /**
     * When not null changes 'GROUP BY' to 'GROUP N BY'
     *
     * @var null|int
     */
    protected ?int $group_n_by = null;

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected array $within_group_order_by = array();

    /**
     * HAVING clause token list (conditions and grouping parenthesis)
     *
     * @var array
     */
    protected array $having = array();

    /**
     * ORDER BY array
     *
     * @var array
     */
    protected array $order_by = array();

    /**
     * When not null it adds an offset
     *
     * @var null|int
     */
    protected ?int $offset = null;

    /**
     * When not null it adds a limit
     *
     * @var null|int
     */
    protected ?int $limit = null;

    /**
     * Value of INTO query for INSERT or REPLACE
     *
     * @var null|string
     */
    protected ?string $into = null;

    /**
     * Array of columns for INSERT or REPLACE
     *
     * @var array
     */
    protected array $columns = array();

    /**
     * Array OF ARRAYS of values for INSERT or REPLACE
     *
     * @var array
     */
    protected array $values = array();

    /**
     * Array arrays containing column and value for SET in UPDATE
     *
     * @var array
     */
    protected array $set = array();

    /**
     * Array of OPTION specific to SphinxQL
     *
     * @var array
     */
    protected array $options = array();

    /**
     * Array of FACETs
     *
     * @var Facet[]
     */
    protected array $facets = array();

    /**
     * The reference to the object that queued itself and created this object
     *
     * @var null|SphinxQL
     */
    protected ?SphinxQL $queue_prev = null;

    /**
     * An array of escaped characters for escapeMatch()
     * @var array
     */
    protected array $escape_full_chars = array(
        '\\' => '\\\\',
        '('  => '\(',
        ')'  => '\)',
        '|'  => '\|',
        '-'  => '\-',
        '!'  => '\!',
        '@'  => '\@',
        '~'  => '\~',
        '"'  => '\"',
        '&'  => '\&',
        '/'  => '\/',
        '^'  => '\^',
        '$'  => '\$',
        '='  => '\=',
        '<'  => '\<',
    );

    /**
     * An array of escaped characters for fullEscapeMatch()
     * @var array
     */
    protected array $escape_half_chars = array(
        '\\' => '\\\\',
        '('  => '\(',
        ')'  => '\)',
        '!'  => '\!',
        '@'  => '\@',
        '~'  => '\~',
        '&'  => '\&',
        '/'  => '\/',
        '^'  => '\^',
        '$'  => '\$',
        '='  => '\=',
        '<'  => '\<',
    );

    /**
     * @param ConnectionInterface|null $connection
     */
    public function __construct(?ConnectionInterface $connection = null)
    {
        $this->connection = $connection;
    }
    
    /**
     * Sets Query Type
     *
     * @return $this
     * @throws SphinxQLException
     */
    public function setType(string $type): self
    {
        $normalizedType = strtolower(trim($type));
        $allowedTypes = array('select', 'insert', 'replace', 'update', 'delete', 'query');
        if (!in_array($normalizedType, $allowedTypes, true)) {
            throw new SphinxQLException(
                'Invalid query type "'.$type.'". Allowed types: '.implode(', ', $allowedTypes).'.'
            );
        }

        $this->type = $normalizedType;

        return $this;
    }

    /**
     * Returns the currently attached connection
     *
     * @returns ConnectionInterface
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Returns detected runtime capabilities for the current connection.
     *
     * @return Capabilities
     * @throws SphinxQLException
     */
    public function getCapabilities(): Capabilities
    {
        if ($this->connection === null) {
            throw new SphinxQLException('getCapabilities() requires an attached connection.');
        }

        return (new Helper($this->connection))->getCapabilities();
    }

    /**
     * Checks whether a named feature is supported.
     *
     * @param string $feature
     *
     * @return bool
     * @throws SphinxQLException
     */
    public function supports($feature): bool
    {
        if ($this->connection === null) {
            throw new SphinxQLException('supports() requires an attached connection.');
        }

        return (new Helper($this->connection))->supports($feature);
    }

    /**
     * Throws when a named feature is not supported.
     *
     * @param string $feature
     * @param string $context
     *
     * @return self
     * @throws SphinxQLException
     */
    public function requireSupport($feature, $context = ''): self
    {
        if ($this->connection === null) {
            throw new SphinxQLException('requireSupport() requires an attached connection.');
        }

        (new Helper($this->connection))->requireSupport($feature, $context);

        return $this;
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
     * @todo make non static
     */
    public static function expr($string = ''): Expression
    {
        return new Expression($string);
    }

    /**
     * Runs the query built
     *
     * @return ResultSetInterface The result of the query
     * @throws DatabaseException
     * @throws ConnectionException
     * @throws SphinxQLException
     */
    public function execute(): ResultSetInterface
    {
        // pass the object so execute compiles it by itself
        return $this->last_result = $this->getConnection()->query($this->compile()->getCompiled());
    }

    /**
     * Executes a batch of queued queries
     *
     * @return MultiResultSetInterface The array of results
     * @throws SphinxQLException In case no query is in queue
     * @throws Exception\DatabaseException
     * @throws ConnectionException
     */
    public function executeBatch(): MultiResultSetInterface
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
    public function enqueue(?SphinxQL $next = null): SphinxQL
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
    public function getQueue(): array
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
    public function getQueuePrev(): ?SphinxQL
    {
        return $this->queue_prev;
    }

    /**
     * Sets the reference to the enqueued object
     *
     * @param SphinxQL $query The object to set as previous
     *
     * @return self
     */
    public function setQueuePrev($query): self
    {
        if (!$query instanceof self) {
            throw new \InvalidArgumentException('setQueuePrev() expects an instance of '.self::class.'.');
        }

        $this->queue_prev = $query;

        return $this;
    }

    /**
     * Returns the result of the last query
     *
     * @return array The result of the last query
     */
    public function getResult(): ResultSetInterface|MultiResultSetInterface|array|int|null
    {
        return $this->last_result;
    }

    /**
     * Returns the latest compiled query
     *
     * @return string The last compiled query
     */
    public function getCompiled(): ?string
    {
        return $this->last_compiled;
    }

    /**
     * Begins transaction
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transactionBegin(): void
    {
        $this->getConnection()->query('BEGIN');
    }

    /**
     * Commits transaction
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transactionCommit(): void
    {
        $this->getConnection()->query('COMMIT');
    }

    /**
     * Rollbacks transaction
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transactionRollback(): void
    {
        $this->getConnection()->query('ROLLBACK');
    }

    /**
     * Runs the compile function
     *
     * @return self
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function compile(): self
    {
        if ($this->type === null) {
            throw new SphinxQLException('Unable to compile query: no query type selected.');
        }

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
            default:
                throw new SphinxQLException('Unable to compile query: unsupported query type "'.$this->type.'".');
        }

        return $this;
    }

    /**
     * @return self
     */
    public function compileQuery(): self
    {
        $this->last_compiled = $this->query;

        return $this;
    }

    /**
     * Compiles the MATCH part of the queries
     * Used by: SELECT, DELETE, UPDATE
     *
     * @return string The compiled MATCH
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     */
    public function compileMatch(): string
    {
        $query = '';

        if (!empty($this->match)) {
            $query .= 'WHERE MATCH(';

            $matched = array();

            foreach ($this->match as $match) {
                $pre = '';
                if ($match['column'] instanceof \Closure) {
                    $sub = new MatchBuilder($this);
                    call_user_func($match['column'], $sub);
                    $pre .= $sub->compile()->getCompiled();
                } elseif ($match['column'] instanceof MatchBuilder) {
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

                if ($pre !== '') {
                    $matched[] = '('.$pre.')';
                }
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
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileWhere(): string
    {
        $compiled = $this->compileBooleanClause($this->where, 'where');
        if ($compiled === '') {
            return '';
        }

        if (empty($this->match)) {
            return 'WHERE '.$compiled.' ';
        }

        return 'AND '.$compiled.' ';
    }

    /**
     * @param array $filter
     *
     * @return string
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileFilterCondition(array $filter): string
    {
        $query = '';

        if (!empty($filter)) {
            if (strtoupper($filter['operator']) === 'BETWEEN') {
                $query .= $filter['column'];
                $query .= ' BETWEEN ';
                $query .= $this->getConnection()->quote($filter['value'][0]).' AND '
                    .$this->getConnection()->quote($filter['value'][1]).' ';
            } else {
                // id can't be quoted!
                if ($filter['column'] === 'id') {
                    $query .= 'id ';
                } else {
                    $query .= $filter['column'].' ';
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
     * @return self
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    public function compileSelect(): self
    {
        $query = '';

        if ($this->type == 'select') {
            $query .= 'SELECT ';

            if (!empty($this->select)) {
                $query .= implode(', ', $this->select).' ';
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
                $query .= 'FROM '.implode(', ', $this->from).' ';
            }
        }

        if (!empty($this->joins)) {
            $query .= $this->compileJoins();
        }

        $query .= $this->compileMatch().$this->compileWhere();

        if (!empty($this->group_by)) {
            $query .= 'GROUP ';
            if ($this->group_n_by !== null) {
                $query .= $this->group_n_by.' ';
            }
            $query .= 'BY '.implode(', ', $this->group_by).' ';
        }

        if (!empty($this->within_group_order_by)) {
            $query .= 'WITHIN GROUP ORDER BY ';

            $order_arr = array();

            foreach ($this->within_group_order_by as $order) {
                $order_sub = $order['column'].' ';

                if ($order['direction'] !== null) {
                    $order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
                }

                $order_arr[] = $order_sub;
            }

            $query .= implode(', ', $order_arr).' ';
        }

        if (!empty($this->having)) {
            $query .= 'HAVING '.$this->compileBooleanClause($this->having, 'having').' ';
        }

        if (!empty($this->order_by)) {
            $query .= 'ORDER BY ';

            $order_arr = array();

            foreach ($this->order_by as $order) {
                $order_sub = $order['column'].' ';

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

                $options[] = $option['name'].' = '.$option['value'];
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
     * @return self
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileInsert(): self
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
            $query .= '('.implode(', ', $this->columns).') ';
        }

        if (!empty($this->values)) {
            $query .= 'VALUES ';
            $query_sub = array();

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
     * @return self
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileUpdate(): self
    {
        if ($this->into === null) {
            throw new SphinxQLException('update() requires into($index) before compile() or execute().');
        }

        $query = 'UPDATE ';

        $query .= $this->into.' ';

        if (!empty($this->set)) {
            $query .= 'SET ';

            $query_sub = array();

            foreach ($this->set as $column => $value) {
                // MVA support
                if (is_array($value)) {
                    $query_sub[] = $column
                        .' = ('.implode(', ', $this->getConnection()->quoteArr($value)).')';
                } else {
                    $query_sub[] = $column
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
     * @return self
     * @throws ConnectionException
     * @throws DatabaseException
     */
    public function compileDelete(): self
    {
        $query = 'DELETE ';

        if (!empty($this->from)) {
            $query .= 'FROM '.$this->from[0].' ';
        }

        if (!empty($this->match)) {
            $query .= $this->compileMatch();
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
     * @return self
     */
    public function query(string $sql): self
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
     * @return self
     */
    public function select($columns = null): self
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
     * Alters which arguments to select
     *
     * Query is assumed to be in SELECT mode
     * See select() for usage
     *
     * @param array|string $columns Array or multiple string arguments containing column names
     *
     * @return self
     */
    public function setSelect($columns = null): self
    {
        if (is_array($columns)) {
            $this->select = $columns;
        } else {
            $this->select = \func_get_args();
        }

        return $this;
    }

    /**
     * Get the columns staged to select
     *
     * @return array
     */
    public function getSelect(): array
    {
        return $this->select;
    }

    /**
     * Activates the INSERT mode
     *
     * @return self
     */
    public function insert(): self
    {
        $this->reset();
        $this->type = 'insert';

        return $this;
    }

    /**
     * Activates the REPLACE mode
     *
     * @return self
     */
    public function replace(): self
    {
        $this->reset();
        $this->type = 'replace';

        return $this;
    }

    /**
     * Activates the UPDATE mode
     *
     * @param null|string $index The index to update into (optional, can be set later with into())
     *
     * @return self
     */
    public function update($index = null): self
    {
        $this->reset();
        $this->type = 'update';

        if ($index !== null) {
            $this->into($index);
        }

        return $this;
    }

    /**
     * Activates the DELETE mode
     *
     * @return self
     */
    public function delete(): self
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
     * @return self
     */
    public function from($array = null): self
    {
        if ($array === null) {
            throw new SphinxQLException('from() requires one or more indexes, a subquery, or a closure.');
        }

        if (is_string($array)) {
            $indexes = \func_get_args();
            foreach ($indexes as $index) {
                if (!is_string($index) || trim($index) === '') {
                    throw new SphinxQLException('from() index names must be non-empty strings.');
                }
            }

            $this->from = $indexes;

            return $this;
        }

        if (is_array($array)) {
            if (empty($array)) {
                throw new SphinxQLException('from() index list cannot be empty.');
            }

            foreach ($array as $index) {
                if (!is_string($index) || trim($index) === '') {
                    throw new SphinxQLException('from() index names must be non-empty strings.');
                }
            }

            $this->from = $array;

            return $this;
        }

        if ($array instanceof \Closure || $array instanceof SphinxQL) {
            $this->from = $array;

            return $this;
        }

        throw new SphinxQLException('from() expects string indexes, an array of indexes, a subquery, or a closure.');
    }

    /**
     * Adds a JOIN clause to the current SELECT query.
     *
     * @param string $table
     * @param string $left
     * @param string $operator
     * @param string $right
     * @param string $type
     *
     * @return self
     */
    public function join($table, $left, $operator, $right, $type = 'INNER'): self
    {
        if (!is_string($table) || trim($table) === '') {
            throw new SphinxQLException('join() table must be a non-empty string.');
        }
        if (!is_string($left) || trim($left) === '') {
            throw new SphinxQLException('join() left operand must be a non-empty string.');
        }
        if (!is_string($operator) || trim($operator) === '') {
            throw new SphinxQLException('join() operator must be a non-empty string.');
        }
        if (!is_string($right) || trim($right) === '') {
            throw new SphinxQLException('join() right operand must be a non-empty string.');
        }

        $joinType = strtoupper(trim((string) $type));
        if (!in_array($joinType, array('INNER', 'LEFT', 'RIGHT'), true)) {
            throw new SphinxQLException('join() type must be one of: INNER, LEFT, RIGHT.');
        }

        $this->joins[] = array(
            'type' => $joinType,
            'table' => $table,
            'left' => $left,
            'operator' => strtoupper(trim($operator)),
            'right' => $right,
        );

        return $this;
    }

    /**
     * Adds an INNER JOIN clause.
     *
     * @param string $table
     * @param string $left
     * @param string $operator
     * @param string $right
     *
     * @return self
     */
    public function innerJoin($table, $left, $operator, $right): self
    {
        return $this->join($table, $left, $operator, $right, 'INNER');
    }

    /**
     * Adds a LEFT JOIN clause.
     *
     * @param string $table
     * @param string $left
     * @param string $operator
     * @param string $right
     *
     * @return self
     */
    public function leftJoin($table, $left, $operator, $right): self
    {
        return $this->join($table, $left, $operator, $right, 'LEFT');
    }

    /**
     * Adds a RIGHT JOIN clause.
     *
     * @param string $table
     * @param string $left
     * @param string $operator
     * @param string $right
     *
     * @return self
     */
    public function rightJoin($table, $left, $operator, $right): self
    {
        return $this->join($table, $left, $operator, $right, 'RIGHT');
    }

    /**
     * Adds a CROSS JOIN clause.
     *
     * @param string $table
     *
     * @return self
     */
    public function crossJoin($table): self
    {
        if (!is_string($table) || trim($table) === '') {
            throw new SphinxQLException('crossJoin() table must be a non-empty string.');
        }

        $this->joins[] = array(
            'type' => 'CROSS',
            'table' => $table,
        );

        return $this;
    }
    
    /**
     * MATCH field IN (values)
     *
     * @param string $field  The field
     * @param array  $values Values for IN clause
     *
     * @return Match
     */
    public function matchFieldIN(string $field, array $values) : Match {
        $match = (new Match($this))->field($field);

        /**
         * Reindex array
         */
        $values = array_values(array_unique($values));

        $count  = count($values);
        for ($n = 0; $n < $count; $n++) {
            if (!$n) {
                $match->match($values[$n]);
            } else {
                $match->orMatch($values[$n]);
            }
        }
        return $match;
    }

    /**
     * Wrap match into another match helper to build complex (A OR (B AND C)) matches
     *
     * @param Match[] $matches Matches to group up with AND
     *
     * @return Match
     */    
    public function matchAndMatch(array $matches) {
        $match = new Match($this);
        foreach ($matches as $m) {
            $match->match($m);
        }
        return $match;
    }

    /**
     * MATCH clause (Sphinx-specific)
     *
     * @param mixed  $column The column name (can be array, string, Closure, or MatchBuilder)
     * @param string $value  The value
     * @param bool   $half   Exclude ", |, - control characters from being escaped
     *
     * @return self
     */
    public function match($column, $value = null, $half = false): self
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
     * @param string                                      $column   The column name
     * @param Expression|string|null|bool|array|int|float $operator The operator to use (if value is not null, you can
     *      use only string)
     * @param Expression|string|null|bool|array|int|float $value    The value to check against
     *
     * @return self
     */
    public function where($column, $operator, $value = null): self
    {
        $this->where[] = array(
            'type' => 'condition',
            'boolean' => 'AND',
            'condition' => $this->createFilterCondition('where', $column, $operator, $value),
        );

        return $this;
    }

    /**
     * Adds an OR WHERE condition.
     *
     * @param string                                      $column
     * @param Expression|string|null|bool|array|int|float $operator
     * @param Expression|string|null|bool|array|int|float $value
     *
     * @return self
     */
    public function orWhere($column, $operator, $value = null): self
    {
        $this->where[] = array(
            'type' => 'condition',
            'boolean' => 'OR',
            'condition' => $this->createFilterCondition('orWhere', $column, $operator, $value),
        );

        return $this;
    }

    /**
     * Opens a grouped WHERE clause.
     *
     * @param string $boolean
     *
     * @return self
     */
    public function whereOpen($boolean = 'AND'): self
    {
        $this->where[] = array(
            'type' => 'open',
            'boolean' => $this->normalizeBooleanOperator($boolean, 'whereOpen'),
        );

        return $this;
    }

    /**
     * Opens a grouped WHERE clause joined with OR.
     *
     * @return self
     */
    public function orWhereOpen(): self
    {
        return $this->whereOpen('OR');
    }

    /**
     * Closes a grouped WHERE clause.
     *
     * @return self
     */
    public function whereClose(): self
    {
        $this->where[] = array('type' => 'close');

        return $this;
    }

    /**
     * GROUP BY clause
     * Adds to the previously added columns
     *
     * @param string $column A column to group by
     *
     * @return self
     */
    public function groupBy($column): self
    {
        $this->group_by[] = $column;

        return $this;
    }

    /**
     * GROUP N BY clause (SphinxQL-specific)
     * Changes 'GROUP BY' into 'GROUP N BY'
     *
     * @param int $n Number of items per group
     *
     * @return self
     */
    public function groupNBy($n): self
    {
        if (filter_var($n, FILTER_VALIDATE_INT) === false || (int) $n <= 0) {
            throw new SphinxQLException('groupNBy() requires a positive integer.');
        }

        $this->group_n_by = (int) $n;

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
     * @return self
     */
    public function withinGroupOrderBy($column, $direction = null): self
    {
        if (!is_string($column) || trim($column) === '') {
            throw new SphinxQLException('withinGroupOrderBy() column must be a non-empty string.');
        }

        $this->within_group_order_by[] = array(
            'column' => $column,
            'direction' => $this->normalizeDirection($direction, 'withinGroupOrderBy')
        );

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
     * @param string $column   The column name
     * @param string $operator The operator to use
     * @param string $value    The value to check against
     *
     * @return self
     */
    public function having($column, $operator, $value = null): self
    {
        $this->having[] = array(
            'type' => 'condition',
            'boolean' => 'AND',
            'condition' => $this->createFilterCondition('having', $column, $operator, $value),
        );

        return $this;
    }

    /**
     * Adds an OR HAVING condition.
     *
     * @param string                                      $column
     * @param Expression|string|null|bool|array|int|float $operator
     * @param Expression|string|null|bool|array|int|float $value
     *
     * @return self
     */
    public function orHaving($column, $operator, $value = null): self
    {
        $this->having[] = array(
            'type' => 'condition',
            'boolean' => 'OR',
            'condition' => $this->createFilterCondition('orHaving', $column, $operator, $value),
        );

        return $this;
    }

    /**
     * Opens a grouped HAVING clause.
     *
     * @param string $boolean
     *
     * @return self
     */
    public function havingOpen($boolean = 'AND'): self
    {
        $this->having[] = array(
            'type' => 'open',
            'boolean' => $this->normalizeBooleanOperator($boolean, 'havingOpen'),
        );

        return $this;
    }

    /**
     * Opens a grouped HAVING clause joined with OR.
     *
     * @return self
     */
    public function orHavingOpen(): self
    {
        return $this->havingOpen('OR');
    }

    /**
     * Closes a grouped HAVING clause.
     *
     * @return self
     */
    public function havingClose(): self
    {
        $this->having[] = array('type' => 'close');

        return $this;
    }

    /**
     * ORDER BY clause
     * Adds to the previously added columns
     *
     * @param string $column    The column to order on
     * @param string $direction The ordering direction (asc/desc)
     *
     * @return self
     */
    public function orderBy($column, $direction = null): self
    {
        if (!is_string($column) || trim($column) === '') {
            throw new SphinxQLException('orderBy() column must be a non-empty string.');
        }

        $this->order_by[] = array(
            'column' => $column,
            'direction' => $this->normalizeDirection($direction, 'orderBy')
        );

        return $this;
    }

    /**
     * Adds ORDER BY KNN(...) clause expression.
     *
     * @param string      $field
     * @param int|string  $k
     * @param array       $vector
     * @param string|null $direction
     *
     * @return self
     */
    public function orderByKnn($field, $k, array $vector, $direction = 'ASC'): self
    {
        if (!is_string($field) || trim($field) === '') {
            throw new SphinxQLException('orderByKnn() field must be a non-empty string.');
        }
        if (filter_var($k, FILTER_VALIDATE_INT) === false || (int) $k <= 0) {
            throw new SphinxQLException('orderByKnn() k must be a positive integer.');
        }
        if (empty($vector)) {
            throw new SphinxQLException('orderByKnn() vector must be a non-empty array.');
        }

        $encodedVector = json_encode(array_values($vector));
        if ($encodedVector === false) {
            throw new SphinxQLException('orderByKnn() vector could not be JSON encoded.');
        }

        $this->order_by[] = array(
            'column' => 'KNN('.$field.', '.((int) $k).', '.$encodedVector.')',
            'direction' => $this->normalizeDirection($direction, 'orderByKnn')
        );

        return $this;
    }

    /**
     * LIMIT clause
     * Supports also LIMIT offset, limit
     *
     * @param int      $offset Offset if $limit is specified, else limit
     * @param null|int $limit  The limit to set, null for no limit
     *
     * @return self
     */
    public function limit($offset, $limit = null): self
    {
        if ($limit === null) {
            if (filter_var($offset, FILTER_VALIDATE_INT) === false || (int) $offset < 0) {
                throw new SphinxQLException('limit() requires a non-negative integer.');
            }

            $this->limit = (int) $offset;

            return $this;
        }

        if (filter_var($offset, FILTER_VALIDATE_INT) === false || (int) $offset < 0) {
            throw new SphinxQLException('limit() offset must be a non-negative integer.');
        }
        if (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 0) {
            throw new SphinxQLException('limit() limit must be a non-negative integer.');
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
     * @return self
     */
    public function offset($offset): self
    {
        if (filter_var($offset, FILTER_VALIDATE_INT) === false || (int) $offset < 0) {
            throw new SphinxQLException('offset() requires a non-negative integer.');
        }

        $this->offset = (int) $offset;

        return $this;
    }

    /**
     * OPTION clause (SphinxQL-specific)
     * Used by: SELECT
     *
     * @param string                                      $name  Option name
     * @param Expression|array|string|int|bool|float|null $value Option value
     *
     * @return self
     */
    public function option($name, $value): self
    {
        if (!is_string($name) || trim($name) === '') {
            throw new SphinxQLException('option() name must be a non-empty string.');
        }

        $this->options[] = array('name' => $name, 'value' => $value);

        return $this;
    }

    /**
     * INTO clause
     * Used by: INSERT, REPLACE
     *
     * @param string $index The index to insert/replace into
     *
     * @return self
     */
    public function into($index): self
    {
        if (!is_string($index) || trim($index) === '') {
            throw new SphinxQLException('into() index must be a non-empty string.');
        }

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
     * @return self
     */
    public function columns($array = array()): self
    {
        if (is_array($array)) {
            if (empty($array)) {
                throw new SphinxQLException('columns() requires at least one column.');
            }

            foreach ($array as $column) {
                if (!is_string($column) || trim($column) === '') {
                    throw new SphinxQLException('columns() values must be non-empty strings.');
                }
            }

            $this->columns = $array;
        } else {
            $columns = \func_get_args();
            foreach ($columns as $column) {
                if (!is_string($column) || trim($column) === '') {
                    throw new SphinxQLException('columns() values must be non-empty strings.');
                }
            }

            $this->columns = $columns;
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
     * @return self
     */
    public function values($array): self
    {
        if (is_array($array)) {
            if (empty($array)) {
                throw new SphinxQLException('values() requires at least one value.');
            }
            $this->values[] = $array;
        } else {
            $values = \func_get_args();
            if (empty($values)) {
                throw new SphinxQLException('values() requires at least one value.');
            }
            $this->values[] = $values;
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
     * @return self
     */
    public function value($column, $value): self
    {
        if (!is_string($column) || trim($column) === '') {
            throw new SphinxQLException('value() column must be a non-empty string.');
        }

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
     * @return self
     */
    public function set($array): self
    {
        if (!is_array($array) || empty($array)) {
            throw new SphinxQLException('set() requires a non-empty associative array.');
        }

        if ($this->columns === array_keys($array)) {
            $this->values($array);
        } else {
            foreach ($array as $key => $item) {
                $this->value($key, $item);
            }
        }

        return $this;
    }

    /**
     * Allows passing an array with the key as column and value as value
     * Used in: INSERT, REPLACE, UPDATE
     *
     * @param Facet $facet
     *
     * @return self
     */
    public function facet($facet): self
    {
        if (!$facet instanceof Facet) {
            throw new SphinxQLException('facet() expects an instance of '.Facet::class.'.');
        }

        $this->facets[] = $facet;

        return $this;
    }

    /**
     * Sets the characters used for escapeMatch().
     *
     * @param array $array The array of characters to escape
     *
     * @return self
     */
    public function setFullEscapeChars($array = array()): self
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
     * @return self
     */
    public function setHalfEscapeChars($array = array()): self
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
    public function compileEscapeChars($array = array()): array
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
    public function escapeMatch($string): string
    {
        if (is_null($string)) {
            return '';
        }

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
    public function halfEscapeMatch($string): string
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
     * @param string                                      $method
     * @param string                                      $column
     * @param Expression|string|null|bool|array|int|float $operator
     * @param Expression|string|null|bool|array|int|float $value
     *
     * @return array
     * @throws SphinxQLException
     */
    private function createFilterCondition($method, $column, $operator, $value = null): array
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (!is_string($column) || trim($column) === '') {
            throw new SphinxQLException($method.'() column must be a non-empty string.');
        }

        if (!is_string($operator) || trim($operator) === '') {
            throw new SphinxQLException($method.'() operator must be a non-empty string.');
        }

        $normalizedOperator = strtoupper(trim($operator));
        if (in_array($normalizedOperator, array('IN', 'NOT IN'), true)) {
            if (!is_array($value) || count($value) === 0) {
                throw new SphinxQLException($method.'() operator '.$normalizedOperator.' requires a non-empty array value.');
            }
        }

        if ($normalizedOperator === 'BETWEEN') {
            if (!is_array($value) || count($value) !== 2) {
                throw new SphinxQLException($method.'() operator BETWEEN requires an array with exactly 2 values.');
            }
        }

        return array(
            'column' => $column,
            'operator' => $normalizedOperator,
            'value' => $value,
        );
    }

    /**
     * @param string|null $boolean
     * @param string      $method
     *
     * @return string
     * @throws SphinxQLException
     */
    private function normalizeBooleanOperator($boolean, $method): string
    {
        if ($boolean === null) {
            return 'AND';
        }

        if (!is_string($boolean) || trim($boolean) === '') {
            throw new SphinxQLException($method.'() boolean must be one of: AND, OR.');
        }

        $normalized = strtoupper(trim($boolean));
        if (!in_array($normalized, array('AND', 'OR'), true)) {
            throw new SphinxQLException($method.'() boolean must be one of: AND, OR.');
        }

        return $normalized;
    }

    /**
     * @param array  $tokens
     * @param string $context
     *
     * @return string
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws SphinxQLException
     */
    private function compileBooleanClause(array $tokens, $context): string
    {
        if (empty($tokens)) {
            return '';
        }

        $query = '';
        $openGroups = 0;
        $prevType = null;
        $hasCondition = false;

        foreach ($tokens as $token) {
            if (!isset($token['type'])) {
                // Legacy compatibility with pre-tokenized conditions.
                $token = array(
                    'type' => 'condition',
                    'boolean' => 'AND',
                    'condition' => $token,
                );
            }

            if ($token['type'] === 'open') {
                $boolean = isset($token['boolean']) ? $this->normalizeBooleanOperator($token['boolean'], $context.'Open') : 'AND';
                if ($prevType === 'condition' || $prevType === 'close') {
                    $query .= $boolean.' ';
                } elseif ($prevType === null && $boolean === 'OR') {
                    throw new SphinxQLException('Cannot start '.$context.' clause with OR group.');
                }

                $query .= '( ';
                $openGroups++;
                $prevType = 'open';
                continue;
            }

            if ($token['type'] === 'close') {
                if ($openGroups <= 0) {
                    throw new SphinxQLException('Unbalanced '.$context.' clause: unexpected closing parenthesis.');
                }
                if ($prevType === 'open') {
                    throw new SphinxQLException('Empty parenthesis group is not allowed in '.$context.' clause.');
                }

                $query .= ') ';
                $openGroups--;
                $prevType = 'close';
                continue;
            }

            if ($token['type'] !== 'condition' || !isset($token['condition'])) {
                throw new SphinxQLException('Invalid '.$context.' token.');
            }

            $boolean = isset($token['boolean']) ? $this->normalizeBooleanOperator($token['boolean'], $context) : 'AND';
            if ($prevType === 'condition' || $prevType === 'close') {
                $query .= $boolean.' ';
            } elseif ($prevType === null && $boolean === 'OR') {
                throw new SphinxQLException('Cannot start '.$context.' clause with OR.');
            }

            $query .= trim($this->compileFilterCondition($token['condition'])).' ';
            $hasCondition = true;
            $prevType = 'condition';
        }

        if ($openGroups !== 0) {
            throw new SphinxQLException('Unbalanced '.$context.' clause: missing closing parenthesis.');
        }

        if (!$hasCondition) {
            throw new SphinxQLException('Empty '.$context.' clause is not allowed.');
        }

        return trim($query);
    }

    /**
     * @return string
     */
    private function compileJoins(): string
    {
        $compiled = '';

        foreach ($this->joins as $join) {
            if ($join['type'] === 'CROSS') {
                $compiled .= 'CROSS JOIN '.$join['table'].' ';
                continue;
            }

            $compiled .= $join['type'].' JOIN '.$join['table'].' ON '.$join['left'].' '.$join['operator'].' '.$join['right'].' ';
        }

        return $compiled;
    }

    /**
     * @param string|null $direction
     * @param string      $method
     *
     * @return string|null
     * @throws SphinxQLException
     */
    private function normalizeDirection($direction, $method): ?string
    {
        if ($direction === null) {
            return null;
        }

        if (!is_string($direction) || trim($direction) === '') {
            throw new SphinxQLException($method.'() direction must be one of: ASC, DESC, or null.');
        }

        $normalized = strtoupper(trim($direction));
        if (!in_array($normalized, array('ASC', 'DESC'), true)) {
            throw new SphinxQLException($method.'() direction must be one of: ASC, DESC, or null.');
        }

        return $normalized;
    }

    /**
     * Clears the existing query build for new query when using the same SphinxQL instance.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->query = null;
        $this->select = array();
        $this->from = array();
        $this->joins = array();
        $this->where = array();
        $this->match = array();
        $this->group_by = array();
        $this->group_n_by = null;
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

    /**
     * @return self
     */
    public function resetWhere(): self
    {
        $this->where = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetJoins(): self
    {
        $this->joins = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetMatch(): self
    {
        $this->match = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetGroupBy(): self
    {
        $this->group_by = array();
        $this->group_n_by = null;

        return $this;
    }

    /**
     * @return self
     */
    public function resetWithinGroupOrderBy(): self
    {
        $this->within_group_order_by = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetFacets(): self
    {
        $this->facets = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetHaving(): self
    {
        $this->having = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetOrderBy(): self
    {
        $this->order_by = array();

        return $this;
    }

    /**
     * @return self
     */
    public function resetOptions(): self
    {
        $this->options = array();

        return $this;
    }
}
