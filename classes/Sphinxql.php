<?php
namespace Foolz\Sphinxql;

class SphinxqlException extends \Exception {}
class SphinxqlDatabaseException extends SphinxqlException {}


/**
 * This class is a Query Builder for SphinxQL, 
 * inspired by the FuelPHP Query Builder.
 */
class Sphinxql extends SphinxqlConnection
{
	/**
	 * The last result object
	 * 
	 * @var type 
	 */
	protected $last_result = null;
	
	/**
	 * The last compiled query
	 * 
	 * @var type 
	 */
	protected $last_compiled = null;

	/**
	 * The last choosen method (select, insert, replace, update, delete)
	 *
	 * @var string 
	 */
	protected $type = null;

	/**
	 * Array of select elements that will be comma separated
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
	 * Ready for use queries
	 *
	 * @var type 
	 */
	protected static $show_queries = array(
		'meta' => 'SHOW META',
		'warnings' => 'SHOW WARNINGS',
		'status' => 'SHOW STATUS',
		'tables' => 'SHOW TABLES',
		'variables' => 'SHOW VARIABLES',
		'variables_session' => 'SHOW SESSION VARIABLES',
		'variables_global' => 'SHOW GLOBAL VARIABLES',
	);
	
	
	/**
	 * Catches static select, insert, replace, update, delete
	 * Used for the SHOW queries
	 * 
	 * @param string $method
	 * @param array $parameters
	 * @return array
	 * @throws \BadMethodCallException
	 */
	public static function __callStatic($method, $parameters)
	{
		if (in_array($method, array('select', 'insert', 'replace', 'update', 'delete')))
		{
			$new = static::forge();
			return \call_user_func_array(array($new, 'do'.ucfirst($method)), $parameters);
		}
		
		if (isset(static::$show_queries[$method]))
		{
			$new = static::forge();
			$ordered = array();
			$result = $new->query(static::$show_queries[$method]);
			foreach ($result as $item)
			{
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
	 *		$sq->where('time', '>', Sphinxql::expr('CURRENT_TIMESTAMP'));
	 *		// WHERE `time` > CURRENT_TIMESTAMP
	 * 
	 * @param type $string
	 * @return \Foolz\Sphinxql\SphinxqlExpression
	 */
	public static function expr($string = '')
	{
		return new SphinxqlExpression($string);
	}


	/**
	 * Runs the query built
	 * 
	 * @return type
	 */
	public function execute()
	{
		// pass the object so execute compiles it by itself
		return $this->last_result = static::query($this->compile()->getCompiled());
	}


	/**
	 * Returns the result of the last query
	 * 
	 * @return array
	 */
	public function getResult()
	{
		return $this->last_result;
	}
	

	/**
	 * Returns the latest compiled query
	 * 
	 * @return type
	 */
	public function getCompiled()
	{
		return $this->last_compiled;
	}

	
	/**
	 * SET syntax
	 * 
	 * @param string $name
	 * @param array|string|int $value
	 * @param bool $global
	 * @return array
	 */
	public static function setVariable($name, $value, $global = false)
	{
		$query = 'SET '.$this->quoteIdentifier($name).' ';
		
		if ($global)
		{
			$query .= 'GLOBAL ';
		}
		
		if (is_array($value))
		{
			$query .= '('.implode(', ', $this->quoteArr($value)).')';
		}
		else
		{
			$query .= $this->quote($value);
		}
		
		static::query($query);
		return $this;
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
	 * @param string $data
	 * @param string $index
	 * @param array $extra
	 * @return array
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
	 * @param string $text
	 * @param string $index
	 * @param null|string $hits
	 * @return array
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
	 * @param string $index
	 */
	public static function describe($index)
	{
		return static::query('DESCRIBE '.$this->quoteIdentifier($index));
	}
	
	
	/**
	 * CREATE FUNCTION syntax
	 * 
	 * @param string $udf_name
	 * @param string $returns INT|BIGINT|FLOAT
	 * @param string $soname
	 */
	public static function createFunction($udf_name, $returns, $soname)
	{
		return static::query('CREATE FUNCTION '.$this->quoteIdentifier($udf_name).
			' RETURNS '.$returns.' SONAME '.$this->quote($soname));
	}
	
	
	/**
	 * DROP FUNCTION syntax
	 * 
	 * @param string $udf_name
	 */
	public static function dropFunction($udf_name)
	{
		return static::query('DROP FUNCTION '.$this->quoteIdentifier($udf_name));
	}
	
	
	/**
	 * ATTACH INDEX * TO RTINDEX * syntax
	 * 
	 * @param string $disk_index
	 * @param string $rt_index
	 * @return array
	 */
	public static function attachIndex($disk_index, $rt_index)
	{
		return static::query('ATTACH INDEX '.$this->quoteIdentifier($disk_index).
			' TO RTINDEX '. $this->quoteIdentifier());
	}
	
	
	/**
	 * FLUSH RTINDEX syntax
	 * 
	 * @param string $index
	 * @return array
	 */
	public static function flushRtindex($index)
	{
		return static::query('FLUSH RTINDEX '.$this->quoteIdentifier($index));
	}
	
	
	/**
	 * Runs the compile function
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @return string
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
					$pre .= $this->halfEscapeString($match['value']);
				}
				else
				{
					$pre .= $this->escapeString($match['value']);
				}
				
				$pre .= ' ';
			}

			$query .= $this->escape($pre).") ";
		}
		
		return $query;
	}
	
	
	/**
	 * Compiles the WHERE part of the queries
	 * It interacts with the MATCH() and of course isn't usable stand-alone
	 * Used by: SELECT, DELETE, UPDATE
	 * 
	 * @return string
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
			foreach ($this->where as $key => $where)
			{
				if (in_array($where['ext_operator'], array('AND (', 'OR (', ')')))
				{
					// if match is not empty we've got to use an operator 
					if ($key == 0 || ! empty($this->match))
					{
						$query .= '(';
					}
					else
					{
						$query .= $where['ext_operator'].' ';
					}
					continue;
				}

				if ($key > 0 || ! empty($this->match))
				{
					$query .= $where['ext_operator'].' '; // AND/OR
				}

				if (strtoupper($where['operator']) === 'BETWEEN')
				{
					$query .= $this->quoteIdentifier($where['column']);
					$query .=' BETWEEN ';
					$query .= $this->quote($where['value'][0]).' AND '.$this->quote($where['value'][1]).' ';
				}
				else
				{
					$query .= $this->quoteIdentifier($where['column']).' ';

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
	 * @return \Foolz\Sphinxql\Sphinql
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

		if ($this->limit !== null)
		{
			if ($this->offset === null)
			{
				$this->offset = 0;
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
	 * @return \Foolz\Sphinxql\Sphinql
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
			$query .= implode(', ', $this->quoteIdentifierArr($this->columns)).' ';
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
	 * @return \Foolz\Sphinxql\Sphinql
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
	 * @return \Foolz\Sphinxql\Sphinql
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
	 * @return \Foolz\Sphinxql\Sphinql
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
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param array $array
	 * @return \Foolz\Sphinxql\Sphinql
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
	 * @param type $column
	 * @param type $value
	 * @param type $half
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function match($column, $value, $half = false)
	{
		$this->match[] = array('column' => $column, 'value' => $value, 'half'	=> $half);
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
	 * @param string $column
	 * @param string $operator
	 * @param string $value
	 * @param bool $or
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param string $column
	 * @param string $operator
	 * @param string|int|null|bool $value
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function orWhere($column, $operator, $value = null)
	{
		$this->where($column, $operator, $value, true);
		return $this;
	}

	
	/**
	 * Opens a parenthesis prepended with AND (where necessary)
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function whereOpen()
	{
		$this->where[] = array('ext_operator' => 'AND (');
		return $this;
	}


	/**
	 * Opens a parenthesis prepended with OR (where necessary)
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function orWhereOpen()
	{
		$this->where[] = array('ext_operator' => 'OR (');
		return $this;
	}


	/**
	 * Closes a parenthesis in WHERE
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function whereClose()
	{
		$this->where[] = array('ext_operator' => ')');
		return $this;
	}


	/**
	 * GROUP BY clause
	 * 
	 * @param string $column
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function groupBy($column)
	{
		$this->group_by[] = $column;
		return $this;
	}


	/**
	 * WITHIN GROUP ORDER BY clause (SphinxQL-specific)
	 * Works just like a classic ORDER BY
	 * 
	 * @param string $column
	 * @param string $direction
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function withinGroupOrderBy($column, $direction = null)
	{
		$this->within_group_order_by[] = array('column' => $column, 'direction' => $direction);
		return $this;
	}

	
	/**
	 * ORDER BY clause
	 *  
	 * @param string $column
	 * @param string $direction asc or desc
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param int $offset offset if $limit is specified, else limit
	 * @param int $limit
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param int $offset
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param type $name
	 * @param type $value
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function option($name, $value)
	{
		$this->options[] = array('name'	 => $name, 'value'	 => $value);
		return $this;
	}
	
	
	/**
	 * INTO clause
	 * Used by: INSERT, REPLACE
	 * 
	 * @param string $index
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param array $array
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function columns($array = array())
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
	 * @param type $array
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param type $column
	 * @param type $value
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function value($column, $value)
	{
		if ($this->type === 'insert')
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
	 * @param type $array
	 * @return \Foolz\Sphinxql\Sphinxql
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
	 * @param string $string
	 * @return string
	 */
	public function escapeString($string)
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
	 * @param string $string
	 * @return string
	 */
	public function halfEscapeString($string)
	{
		$from = array('\\', '(', ')', '!', '@', '~', '&', '/', '^', '$', '=');
		$to = array('\\\\', '\(', '\)', '\!', '\@', '\~', '\&', '\/', '\^', '\$', '\=');
		$string	 = str_replace($from, $to, $string);
		
		// close stay quotes
		if(substr_count($string, '"') % 2 !== 0)
		{
			$string .= '"';
		}
		
		$string	 = preg_replace("'\"([^\s]+)-([^\s]*)\"'", "\\1\-\\2", $string);
		return preg_replace("'([^\s]+)-([^\s]*)'", "\"\\1\-\\2\"", $string);
	}

}