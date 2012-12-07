<?php

namespace Foolz\Sphinxql;

class SphinxqlException extends \Exception {}
class SphinxqlDatabaseException extends SphinxqlException {}

/**
 * This class is a Query Builder for SphinxQL,
 * inspired by the FuelPHP Query Builder.
 */
class Sphinxql extends Connection
{
	/**
	 * The last result object
	 *
	 * @var  array
	 */
	protected $last_result = null;

	/**
	 * The last compiled query
	 *
	 * @var  string
	 */
	protected $last_compiled = null;

	/**
	 * The last choosen method (select, insert, replace, update, delete)
	 *
	 * @var  string
	 */
	protected $type = null;

	/**
	 * Array of select elements that will be comma separated
	 *
	 * @var  array
	 */
	protected $select = array();

	/**
	 * From in SphinxQL is the list of indexes that will be used
	 *
	 * @var  array
	 */
	protected $from = array();

	/**
	 * The list of where and parenthesis, must be inserted in order
	 *
	 * @var  array
	 */
	protected $where = array();

	/**
	 * The list of matches for the MATCH function in SphinxQL
	 *
	 * @var  array
	 */
	protected $match = array();

	/**
	 * GROUP BY array to be comma separated
	 *
	 * @var  array
	 */
	protected $group_by = array();

	/**
	 * ORDER BY array
	 *
	 * @var  array
	 */
	protected $within_group_order_by = array();

	/**
	 * ORDER BY array
	 *
	 * @var  array
	 */
	protected $order_by = array();

	/**
	 * When not null it adds an offset
	 *
	 * @var  null|int
	 */
	protected $offset = null;

	/**
	 * When not null it adds a limit
	 *
	 * @var  null|int
	 */
	protected $limit = null;

	/**
	 * Value of INTO query for INSERT or REPLACE
	 *
	 * @var  null|string
	 */
	protected $into = null;

	/**
	 * Array of columns for INSERT or REPLACE
	 *
	 * @var  array
	 */
	protected $columns = array();

	/**
	 * Array OF ARRAYS of values for INSERT or REPLACE
	 *
	 * @var  array
	 */
	protected $values = array();

	/**
	 * Array arrays containing column and value for SET in UPDATE
	 *
	 * @var  array
	 */
	protected $set = array();

	/**
	 * Array of OPTION specific to SphinxQL
	 *
	 * @var  array
	 */
	protected $options = array();

	/**
	 * Ready for use queries
	 *
	 * @var  array
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


	/**
	 * Catches static select, insert, replace, update, delete
	 * Used for the SHOW queries
	 *
	 * @param  string  $method      The method
	 * @param  array   $parameters  The parameters
	 *
	 * @return  array  The result of the SHOW query
	 * @throws  \BadMethodCallException  If there's no such a method
	 */
	public static function __callStatic($method, $parameters)
	{
		if (isset(static::$show_queries[$method]))
		{
			$new = static::forge();
			$ordered = array();
			$result = $new->query(static::$show_queries[$method]);
			if ($method === 'tables')
			{
				return $result;
			}

			foreach ($result as $item)
			{
				$ordered[$item['Variable_name']] = $item['Value'];
			}

			return $ordered;
		}

		throw new \BadMethodCallException($method);
	}

	/**
	 * Begins building a select query
	 * Uses func_get_args to get input
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The new object
	 */
	public static function select()
	{
		if (count(\func_get_args()))
		{
			$new = static::forge();
			return \call_user_func_array(array($new, 'doSelect'), \func_get_args());
		}

		return static::forge()->doSelect();
	}

	/**
	 * Begins building an insert query
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The new object
	 */
	public static function insert()
	{
		return static::forge()->doInsert();
	}

	/**
	 * Begins building a replace query
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The new object
	 */
	public static function replace()
	{
		return static::forge()->doReplace();
	}

	/**
	 * Begins building an update query
	 *
	 * @param  string  $index  The index to update
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The new object
	 */
	public static function update($index)
	{
		return static::forge()->doUpdate($index);
	}

	/**
	 * Begins building a delete query
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The new object
	 */
	public static function delete()
	{
		return static::forge()->doDelete();
	}

	/**
	 * Avoids having the expressions escaped
	 *
	 * Example
	 *		$sq->where('time', '>', Sphinxql::expr('CURRENT_TIMESTAMP'));
	 *		// WHERE `time` > CURRENT_TIMESTAMP
	 *
	 * @param  string  $string  The string to keep unaltered
	 *
	 * @return  \Foolz\Sphinxql\Expression  The new Expression
	 */
	public static function expr($string = '')
	{
		return new Expression($string);
	}


	/**
	 * Runs the query built
	 *
	 * @return  array  The result of the query
	 */
	public function execute()
	{
		// pass the object so execute compiles it by itself
		return $this->last_result = static::query($this->compile()->getCompiled());
	}


	/**
	 * Returns the result of the last query
	 *
	 * @return  array  The result of the last query
	 */
	public function getResult()
	{
		return $this->last_result;
	}


	/**
	 * Returns the latest compiled query
	 *
	 * @return  string  The last compiled query
	 */
	public function getCompiled()
	{
		return $this->last_compiled;
	}


	/**
	 * SET syntax
	 *
	 * @param  string   $name    The name of the variable
	 * @param  mixed    $value   The value o the variable
	 * @param  boolean  $global  True if the variable should be global, false otherwise
	 *
	 * @return  array  The result of the query
	 */
	public static function setVariable($name, $value, $global = false)
	{
		$sq = Sphinxql::forge();

		$query = 'SET ';

		if ($global)
		{
			$query .= 'GLOBAL ';
		}

		$user_var = strpos($name, '@') === 0;

		// if it has an @ it's a user variable and we can't wrap it
		if ($user_var)
		{
			$query .= $name.' ';
		}
		else
		{
			$query .= $sq->quoteIdentifier($name).' ';
		}

		// user variables must always be processed as arrays
		if ($user_var && ! is_array($value))
		{
			$query .= '= ('.$sq->quote($value).')';
		}
		else if (is_array($value))
		{
			$query .= '= ('.implode(', ', $sq->quoteArr($value)).')';
		}
		else
		{
			$query .= '= '.$sq->quote($value);
		}

		static::query($query);
	}


	/**
	 * Begins transaction
	 */
	public static function transactionBegin()
	{
		static::query('BEGIN');
	}


	/**
	 * Commits transaction
	 */
	public static function transactionCommit()
	{
		static::query('COMMIT');
	}


	/**
	 * Rollbacks transaction
	 */
	public static function transactionRollback()
	{
		static::query('ROLLBACK');
	}


	/**
	 * CALL SNIPPETS syntax
	 *
	 * @param  string  $data
	 * @param  string  $index
	 * @param  array   $extra
	 *
	 * @return  array  The result of the query
	 */
	public static function callSnippets($data, $index, $extra = array())
	{
		array_unshift($index, $extra);
		array_unshift($data, $extra);
		return static::query('CALL SNIPPETS('.implode(', ', $this->quoteArr($extra)).')');
	}


	/**
	 * CALL KEYWORDS syntax
	 *
	 * @param  string       $text
	 * @param  string       $index
	 * @param  null|string  $hits
	 *
	 * @return  array  The result of the query
	 */
	public static function callKeywords($text, $index, $hits = null)
	{
		$arr = array($text, $index);
		if ($hits !== null)
		{
			$arr[] = $hits;
		}

		return static::query('CALL KEYWORDS('.implode(', ', $this->quoteArr($arr)).')');
	}


	/**
	 * DESCRIBE syntax
	 *
	 * @param  string  $index  The name of the index
	 *
	 * @return  array  The result of the query
	 */
	public static function describe($index)
	{
		$sq = Sphinxql::forge();
		return static::query('DESCRIBE '.$sq->quoteIdentifier($index));
	}


	/**
	 * CREATE FUNCTION syntax
	 *
	 * @param  string  $udf_name
	 * @param  string  $returns   Whether INT|BIGINT|FLOAT
	 * @param  string  $so_name
	 *
	 * @return  array  The result of the query
	 */
	public static function createFunction($udf_name, $returns, $so_name)
	{
		return static::query('CREATE FUNCTION '.$this->quoteIdentifier($udf_name).
			' RETURNS '.$returns.' SONAME '.$this->quote($so_name));
	}


	/**
	 * DROP FUNCTION syntax
	 *
	 * @param  string  $udf_name
	 *
	 * @return  array  The result of the query
	 */
	public static function dropFunction($udf_name)
	{
		return static::query('DROP FUNCTION '.$this->quoteIdentifier($udf_name));
	}


	/**
	 * ATTACH INDEX * TO RTINDEX * syntax
	 *
	 * @param  string  $disk_index
	 * @param  string  $rt_index
	 *
	 * @return  array  The result of the query
	 */
	public static function attachIndex($disk_index, $rt_index)
	{
		return static::query('ATTACH INDEX '.$this->quoteIdentifier($disk_index).
			' TO RTINDEX '. $this->quoteIdentifier());
	}


	/**
	 * FLUSH RTINDEX syntax
	 *
	 * @param  string  $index
	 *
	 * @return  array  The result of the query
	 */
	public static function flushRtIndex($index)
	{
		return static::query('FLUSH RTINDEX '.$this->quoteIdentifier($index));
	}


	/**
	 * Runs the compile function
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function compile()
	{
		switch ($this->type)
		{
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
		}

		return $this;
	}

	/**
	 * Compiles the MATCH part of the queries
	 * Used by: SELECT, DELETE, UPDATE
	 *
	 * @return  string  The compiled MATCH
	 */
	public function compileMatch()
	{
		$query = '';

		if ( ! empty($this->match))
		{
			$query .= 'WHERE ';
		}

		if ( ! empty($this->match))
		{
			$query .= "MATCH(";

			$pre = '';

			foreach ($this->match as $match)
			{
				$pre .= '@'.$match['column'].' ';

				if ($match['half'])
				{
					$pre .= $this->halfEscapeMatch($match['value']);
				}
				else
				{
					$pre .= $this->escapeMatch($match['value']);
				}

				$pre .= ' ';
			}

			$query .= $this->escape(trim($pre)).") ";
		}

		return $query;
	}


	/**
	 * Compiles the WHERE part of the queries
	 * It interacts with the MATCH() and of course isn't usable stand-alone
	 * Used by: SELECT, DELETE, UPDATE
	 *
	 * @return  string  The compiled WHERE
	 */
	public function compileWhere()
	{
		$query = '';

		if (empty($this->match) && ! empty($this->where))
		{
			$query .= 'WHERE ';
		}

		if ( ! empty($this->where))
		{
			$just_opened = false;

			foreach ($this->where as $key => $where)
			{
				if (in_array($where['ext_operator'], array('AND (', 'OR (', ')')))
				{
					// if match is not empty we've got to use an operator
					if ($key == 0 || ! empty($this->match))
					{
						$query .= '(';

						$just_opened = true;
					}
					else
					{
						$query .= $where['ext_operator'].' ';
					}

					continue;
				}

				if ($key > 0 && ! $just_opened || ! empty($this->match))
				{
					$query .= $where['ext_operator'].' '; // AND/OR
				}

				$just_opened = false;

				if (strtoupper($where['operator']) === 'BETWEEN')
				{
					$query .= $this->quoteIdentifier($where['column']);
					$query .=' BETWEEN ';
					$query .= $this->quote($where['value'][0]).' AND '.$this->quote($where['value'][1]).' ';
				}
				else
				{
					// id can't be quoted!
					if ($where['column'] === 'id')
					{
						$query .= 'id ';
					}
					else
					{
						$query .= $this->quoteIdentifier($where['column']).' ';
					}

					if (strtoupper($where['operator']) === 'IN')
					{
						$query .= 'IN ('.implode(', ', $this->quoteArr($where['value'])).') ';
					}
					else
					{
						$query .= $where['operator'].' '.$this->quote($where['value']).' ';
					}
				}
			}
		}

		return $query;
	}


	/**
	 * Compiles the statements for SELECT
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function compileSelect()
	{
		$query = '';

		if ($this->type == 'select')
		{
			$query .= 'SELECT ';

			if ( ! empty($this->select))
			{
				$query .= implode(', ', $this->quoteIdentifierArr($this->select)).' ';
			}
			else
			{
				$query .= '* ';
			}
		}

		if ( ! empty($this->from))
		{
			$query .= 'FROM '.implode(', ', $this->quoteIdentifierArr($this->from)).' ';
		}

		$query .= $this->compileMatch().$this->compileWhere();

		if ( ! empty($this->group_by))
		{
			$query .= 'GROUP BY '.implode(', ', $this->quoteIdentifierArr($this->group_by)).' ';
		}

		if ( ! empty($this->within_group_order_by))
		{
			$query .= 'ORDER BY ';

			$order_arr = array();

			foreach ($this->within_group_order_by as $order)
			{
				$order_sub = $this->quoteIdentifier($order['column']).' ';

				if ($order['direction'] !== null)
				{
					$order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
				}

				$order_arr[] = $order_sub;
			}

			$query .= implode(', ', $order_arr).' ';
		}

		if ( ! empty($this->order_by))
		{
			$query .= 'ORDER BY ';

			$order_arr = array();

			foreach ($this->order_by as $order)
			{
				$order_sub = $this->quoteIdentifier($order['column']).' ';

				if ($order['direction'] !== null)
				{
					$order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
				}

				$order_arr[] = $order_sub;
			}

			$query .= implode(', ', $order_arr).' ';
		}

		if ($this->limit !== null || $this->offset !== null)
		{
			if ($this->offset === null)
			{
				$this->offset = 0;
			}

			if ($this->limit === null)
			{
				$this->limit = 9999999999999;
			}

			$query .= 'LIMIT '.((int) $this->offset).', '.((int) $this->limit).' ';
		}

		if (!empty($this->options))
		{
			$options = array();
			foreach ($this->options as $option)
			{
				$options[] = $this->quoteIdentifier($option['name']).' = '.$this->quote($option['value']);
			}

			$query .= 'OPTION '.implode(', ', $options);
		}

		$this->last_compiled = $query;

		return $this;
	}


	/**
	 * Compiles the statements for INSERT or REPLACE
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function compileInsert()
	{
		if ($this->type == 'insert')
		{
			$query = 'INSERT ';
		}
		else
		{
			$query = 'REPLACE ';
		}

		if ($this->into !== null)
		{
			$query .= 'INTO '.$this->into.' ';
		}

		if ( ! empty ($this->columns))
		{
			$query .= '('.implode(', ', $this->quoteIdentifierArr($this->columns)).') ';
		}

		if ( ! empty ($this->values))
		{
			$query .= 'VALUES ';
			$query_sub = '';
			foreach($this->values as $value)
			{
				$query_sub[] = '('.implode(', ', $this->quoteArr($value)).')';
			}

			$query .= implode(', ', $query_sub);
		}

		$this->last_compiled = $query;

		return $this;
	}


	/**
	 * Compiles the statements for UPDATE
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function compileUpdate()
	{
		$query = 'UPDATE ';

		if ($this->into !== null)
		{
			$query .= $this->into.' ';
		}

		if ( ! empty($this->set))
		{

			$query .= 'SET ';

			$query_sub = array();

			foreach ($this->set as $column => $value)
			{
				// MVA support
				if (is_array($value))
				{
					$query_sub[] = $this->quoteIdentifier($column).' = ('.implode(', ', $this->queryArr($value)).')';
				}
				else
				{
					$query_sub[] = $this->quoteIdentifier($column).' = '.$this->quote($value);
				}
			}

			$query .= implode(', ', $query_sub).' ';
		}

		$query .= $this->compileMatch().$this->compileWhere();

		$this->last_compiled = $query;

		return $this;
	}


	/**
	 * Compiles the statements for DELETE
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function compileDelete()
	{
		$query = 'DELETE ';

		if ( ! empty($this->from))
		{
			$query .= 'FROM '.$this->from[0].' ';
		}

		if ( ! empty($this->where))
		{
			$query .= $this->compileWhere();
		}

		$this->last_compiled = $query;

		return $this;
	}


	/**
	 * Select the columns
	 * Gets the arguments passed as $sphinxql->select('one', 'two')
	 * Using it without arguments equals to having '*' as argument
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function doSelect()
	{
		if ($this->type !== null)
		{
			$new = static::forge($this->conn);
			\call_user_func_array(array($new, 'select'), \func_get_args());
			return $new;
		}

		$this->type = 'select';
		$this->select = \func_get_args();
		return $this;
	}


	/**
	 * Activates the INSERT mode
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function doInsert()
	{
		if ($this->type !== null)
		{
			$new = static::forge($this->conn);
			$new->insert();
			return $new;
		}

		$this->type = 'insert';
		return $this;
	}


	/**
	 * Activates the REPLACE mode
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function doReplace()
	{
		if ($this->type !== null)
		{
			$new = static::forge($this->conn);
			$new->replace();
			return $new;
		}

		$this->type = 'replace';
		return $this;
	}


	/**
	 * Activates the UPDATE mode
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function doUpdate($index)
	{
		if ($this->type !== null)
		{
			$new = static::forge($this->conn);
			$new->update($index);
			$new->into($index);
			return $new;
		}

		$this->type = 'update';
		$this->into($index);
		return $this;
	}

	/**
	 * Activates the DELETE mode
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function doDelete()
	{
		if ($this->type !== null)
		{
			$new = static::forgeWithConnection($this->conn);
			$new->delete();
			return $new;
		}

		$this->type = 'delete';
		return $this;
	}


	/**
	 * FROM clause (Sphinx-specific since it works with multiple indexes)
	 * func_get_args()-enabled
	 *
	 * @param  array  $array  An array of indexes to use
	 *
	 * @return \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function from($array = null)
	{
		if (is_string($array))
		{
			$this->from = \func_get_args();
		}

		if (is_array($array))
		{
			$this->from = $array;
		}

		return $this;
	}


	/**
	 * MATCH clause (Sphinx-specific)
	 *
	 * @param  string   $column  The column name
	 * @param  string   $value   The value
	 * @param  boolean  $half    Exclude ", |, - control characters from being escaped
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function match($column, $value, $half = false)
	{
		$this->match[] = array('column' => $column, 'value' => $value, 'half' => $half);
		return $this;
	}


	/**
	 * WHERE clause
	 *
	 * Examples:
	 *		$sq->where('column', 'value');
	 *		// WHERE `column` = 'value'
	 *
	 *		$sq->where('column', '=', 'value');
	 *		// WHERE `column` = 'value'
	 *
	 *		$sq->where('column', '>=', 'value')
	 *		// WHERE `column` >= 'value'
	 *
	 *		$sq->where('column', 'IN', array('value1', 'value2', 'value3'));
	 *		// WHERE `column` IN ('value1', 'value2', 'value3')
	 *
	 *		$sq->where('column', 'BETWEEN', array('value1', 'value2'))
	 *		// WHERE `column` BETWEEN 'value1' AND 'value2'
	 *		// WHERE `example` BETWEEN 10 AND 100
	 *
	 * @param  string   $column    The column name
	 * @param  string   $operator  The operator to use
	 * @param  string   $value     The value to check against
	 * @param  boolean  $or        If it should be prepended with OR (true) or AND (false) - not available as for Sphinx 2.0.2
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function where($column, $operator, $value = null, $or = false)
	{
		if ($value === null)
		{
			$value		 = $operator;
			$operator	 = '=';
		}

		$this->where[] = array(
			'ext_operator'	 => $or ? 'OR' : 'AND',
			'column'		 => $column,
			'operator'		 => $operator,
			'value'			 => $value
		);
		return $this;
	}


	/**
	 * OR WHERE - at this time (Sphinx 2.0.2) it's not available
	 *
	 * @param  string  $column    The column name
	 * @param  string  $operator  The operator to use
	 * @param  mixed   $value     The value to compare against
	 *
	 * @return \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function orWhere($column, $operator, $value = null)
	{
		$this->where($column, $operator, $value, true);
		return $this;
	}


	/**
	 * Opens a parenthesis prepended with AND (where necessary)
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function whereOpen()
	{
		$this->where[] = array('ext_operator' => 'AND (');
		return $this;
	}


	/**
	 * Opens a parenthesis prepended with OR (where necessary)
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function orWhereOpen()
	{
		$this->where[] = array('ext_operator' => 'OR (');
		return $this;
	}


	/**
	 * Closes a parenthesis in WHERE
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  string  $column  A column to group by
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  string  $column     The column to group by
	 * @param  string  $direction  The group by direction (asc/desc)
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  string  $column     The column to order on
	 * @param  string  $direction  The ordering direction (asc/desc)
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  int       $offset  Offset if $limit is specified, else limit
	 * @param  null|int  $limit   The limit to set, null for no limit
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function limit($offset, $limit = null)
	{
		if ($limit === null)
		{
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
	 * @param  int  $offset  The offset
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  string  $name   Option name
	 * @param  string  $value  Option value
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  string  $index  The index to insert/replace into
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  array  $array  The array of columns
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function columns(Array $array = array())
	{
		if(is_array($array))
		{
			$this->columns = $array;
		}
		else
		{
			$this->columns = \func_get_args();
		}

		return $this;
	}


	/**
	 * Set VALUES
	 * Used in: INSERT, REPLACE
	 * func_get_args()-enabled
	 *
	 * @param  array  $array  The array of values matching the columns from $this->columns()
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function values($array)
	{
		if (is_array($array))
		{
			$this->values[] = $array;
		}
		else
		{
			$this->values[] = \func_get_args();
		}
		return $this;
	}


	/**
	 * Set column and relative value
	 * Used in: INSERT, REPLACE
	 *
	 * @param  string  $column  The column name
	 * @param  string  $value   The value
	 *
	 * @return  \Foolz\Sphinxql\Sphinxql  The current object
	 */
	public function value($column, $value)
	{
		if ($this->type === 'insert' || $this->type === 'replace')
		{
			$this->columns[] = $column;
			$this->values[0][] = $value;
		}
		else
		{
			$this->set[$column] = $value;
		}

		return $this;
	}


	/**
	 * Allows passing an array with the key as column and value as value
	 * Used in: INSERT, REPLACE, UPDATE
	 *
	 * @param  array  $array  Array of key-values
	 *
	 * @return \Foolz\Sphinxql\Sphinxql  The current object
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
	 * @param  string  $string The string to escape for the MATCH
	 *
	 * @return  string  The escaped string
	 */
	public function escapeMatch($string)
	{
		$from = array('\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '=');
		$to = array('\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\=');
		return str_replace($from, $to, $string);
	}


	/**
	 * Escapes the query for the MATCH() function
	 * Allows some of the control characters to pass through for use with a search field: -, |, "
	 * It also does some tricks to wrap/unwrap within " the string and prevents errors
	 *
	 * @param  string  $string  The string to escape for the MATCH
	 *
	 * @return  string  The escaped string
	 */
	public function halfEscapeMatch($string)
	{
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

		$string	 = str_replace(array_keys($from_to), array_values($from_to), $string);

		// this manages to lower the error rate by a lot
		if (substr_count($string, '"') % 2 !== 0)
		{
			$string .= '"';
		}

		$from_to_preg = array(
			"'\"([^\s]+)-([^\s]*)\"'" => "\\1\-\\2",
			"'([^\s]+)-([^\s]*)'" => "\"\\1\-\\2\""
		);

		$string = preg_replace(array_keys($from_to_preg), array_values($from_to_preg), $string);

		return $string;
	}

}