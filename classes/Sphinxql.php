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
	 * The last choosen method (select, update, insert, delete)
	 *
	 * @var string 
	 */
	protected $type = 'select';

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
	 * Array of OPTION specific to SphinxQL
	 * 
	 * @var array 
	 */
	protected $options = array();

	/**
	 * The last compiled query
	 * 
	 * @var string 
	 */
	protected $last_compiled = array();


	/**
	 * Used for the SHOW queries
	 * 
	 * @param string $method
	 * @param array $parameters
	 * @return array
	 * @throws \BadMethodCallException
	 */
	public function __call($method, $parameters)
	{
		$gets = array(
			'meta' => 'SHOW META',
			'warnings' => 'SHOW WARNINGS',
			'status' => 'SHOW STATUS',
			'tables' => 'SHOW TABLES',
			'variables' => 'SHOW '.(isset($parameters[0]) ? $parameters[0].' ' : '').'VARIABLES',
		);
		
		if (isset($gets[$method]))
		{
			return $this->query($gets[$method]);
		}
		
		throw new \BadMethodCallException;
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
		return $this->last_result = $this->query($this->compile()->get_compiled());
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
	 * Runs the compile function
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function compile()
	{
		$this->{'compile_'.$this->type}();
		return $this;
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
					$query .= $this->quoteIdentifier($where['column']).' '.$where['operator'].' ';

					if (strtoupper($where['operator']) === 'IN')
					{
						$query .= '('.implode(', ', $this->quoteArr($where['value'])).') ';
					}
					else
					{
						$query .= $this->quote($where['value']).' ';
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

		if ( ! empty($this->match) || ! empty($this->where))
		{
			$query .= 'WHERE ';
		}

		if ( ! empty($this->match))
		{
			$used_where = true;

			$query .= "MATCH('";

			foreach ($this->match as $match)
			{
				$query .= '@'.$match['column'].' ';

				if ($match['half'])
				{
					$query .= $this->halfEscapeString($match['value']);
				}
				else
				{
					$query .= $this->escapeString($match['value']);
				}
			}

			$query .= "') ";
		}
		
		$query .= $this->compile_where();

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
			$query .= 'LIMIT '.((int) $this->limit).' ';
		}

		if ($this->offset !== null)
		{
			$query .= 'OFFSET ' . ((int) $this->offset).' ';
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
	 * Compiles the statements for DELETE
	 * 
	 * @return \Foolz\Sphinxql\Sphinql
	 */
	public function compileDelete()
	{
		$query .= 'DELETE ';
		
		if ( ! empty($this->from))
		{
			$query .= 'FROM '.$this->from[0].' ';
		}
		
		if ( ! empty($this->where))
		{
			$query .= $this->compile_where();
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
	public function select()
	{
		$this->type = 'select';
		$this->select = \func_get_args();
		return $this;
	}
	
	
	/**
	 * Activates the INSERT mode
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function insert()
	{
		$this->type = 'insert';
		return $this;
	}
	
	
	/**
	 * Activates the REPLACE mode
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function replace()
	{
		$this->type = 'replace';
		return $this;
	}
	
	
	/**
	 * Activates the UPDATE mode
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function update()
	{
		$this->type = 'update';
		return $this;
	}
	
	/**
	 * Activates the DELETE mode
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function delete()
	{
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
	public function or_where($column, $operator, $value = null)
	{
		$this->where($column, $operator, $value, true);
		return $this;
	}

	
	/**
	 * Opens a parenthesis prepended with AND (where necessary)
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function where_open()
	{
		$this->where[] = array('ext_operator' => 'AND (');
		return $this;
	}


	/**
	 * Opens a parenthesis prepended with OR (where necessary)
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function or_where_open()
	{
		$this->where[] = array('ext_operator' => 'OR (');
		return $this;
	}


	/**
	 * Closes a parenthesis in WHERE
	 * 
	 * @return \Foolz\Sphinxql\Sphinxql
	 */
	public function where_close()
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
	public function group_by($column)
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
	public function within_group_order_by($column, $direction = null)
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
	public function order_by($column, $direction = null)
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
		$this->columns[] = $column;
		$this->values[0][] = $value;
		return $this;
	}

	
	/**
	 * Allows passing an array with the key as column and value as value
	 * Used in: INSERT, REPLACE
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
		$string	 = preg_replace("'\"([^\s]+)-([^\s]*)\"'", "\\1\-\\2", $string);
		return preg_replace("'([^\s]+)-([^\s]*)'", "\"\\1\-\\2\"", $string);
	}

}