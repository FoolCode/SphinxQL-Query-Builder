<?php

namespace Foolz\Sphinxql;

class SphinxqlException extends \Exception
{
	
}

class SphinxqlDatabaseException extends SphinxqlException
{
	
}

class Sphinxql extends SphinxqlConnection
{

	/**
	 * The last choosen method (select, update, insert, delete)
	 *
	 * @var string 
	 */
	protected $_type = 'select';

	/**
	 * Array of select elements that will be comma separated
	 *
	 * @var array
	 */
	protected $_select = array();

	/**
	 * From in SphinxQL is the list of indexes that will be used
	 * 
	 * @var array 
	 */
	protected $_from = array();

	/**
	 * The list of where and parenthesis, must be inserted in order
	 * 
	 * @var array 
	 */
	protected $_where = array();

	/**
	 * The list of matches for the MATCH function in SphinxQL
	 *
	 * @var array
	 */
	protected $_match = array();

	/**
	 * GROUP BY array to be comma separated
	 * 
	 * @var array 
	 */
	protected $_group_by = array();

	/**
	 * ORDER BY array
	 * 
	 * @var array 
	 */
	protected $_order_by = array();

	/**
	 * When not null it adds an offset
	 * 
	 * @var null|int 
	 */
	protected $_offset = null;

	/**
	 * When not null it adds a limit
	 * 
	 * @var null|int 
	 */
	protected $_limit = null;

	/**
	 * Array of OPTION specific to SphinxQL
	 * 
	 * @var array 
	 */
	protected $_options = array();

	/**
	 * The last compiled query
	 * 
	 * @var string 
	 */
	protected $_last_compiled = array();


	public static function expr($string = '')
	{
		return new SphinxqlException($string);
	}


	public function execute()
	{
		// pass the object so execute compiles it by itself
		$this->_last_result = $this->query($this->compile()->get_compiled());
		return $this;
	}


	public function get_result()
	{
		return $this->_last_result;
	}


	public function meta()
	{
		$this->_last_meta = $this->query('SHOW META');
		return $this;
	}


	public function get_meta()
	{
		return $this->_last_meta;
	}


	/**
	 * Compiles the query
	 * 
	 * @return \Foolz\Sphinxql\Sphinql
	 */
	public function compile()
	{
		$query = '';

		if ($this->_type == 'select')
		{
			$query .= 'SELECT ';

			if (!empty($this->_select))
			{
				$query .= implode(', ', $this->quoteIdentifiersArr($this->_select)) . ' ';
			}
			else
			{
				$query .= '* ';
			}
		}

		if (!empty($this->_from))
		{
			$query .= 'FROM ' . implode(', ', $this->quoteIdentifiersArr($this->_from)) . ' ';
		}

		if (!empty($this->_match) || !empty($this->_where))
		{
			$query .= 'WHERE ';
		}

		if (!empty($this->_match))
		{
			$used_where = true;

			$query .= "MATCH('";

			foreach ($this->_match as $match)
			{
				$query .= '@' . $match['column'] . ' ';

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

		if (!empty($this->_where))
		{
			foreach ($this->_where as $key => $where)
			{
				if (in_array($where['ext_operator'], array('AND (', 'OR (', ')')))
				{
					if ($key == 0 || !empty($this->_match))
					{
						$query .= '(';
					}
					else
					{
						$query .= $where['ext_operator'] . ' ';
					}
					continue;
				}

				if ($key > 0 || !empty($this->_match))
				{
					$query .= $where['ext_operator'] . ' '; // AND/OR
				}

				$query .= $this->quoteIdentifier($where['column']) . " " . $where['operator'] . " " . $this->quote($where['value']) . " ";
			}
		}

		if (!empty($this->_group_by))
		{
			$query .= 'GROUP BY ' . implode(', ', $this->quoteIdentifiersArr($this->_group_by)) . ' ';
		}

		if (!empty($this->_order_by))
		{
			$query .= 'ORDER BY ';

			$order_arr = array();

			foreach ($this->_order_by as $order)
			{
				$order_sub = $this->quoteIdentifier($order['column']) . ' ';

				if ($order['direction'] !== null)
				{
					$order_sub .= ((strtolower($order['direction']) === 'desc') ? 'DESC' : 'ASC');
				}

				$order_arr[] = $order_sub;
			}

			$query .= implode(', ', $order_arr) . ' ';
		}

		if ($this->_limit !== null)
		{
			$query .= 'LIMIT ' . ((int) $this->_limit) . ' ';
		}

		if ($this->_offset !== null)
		{
			$query .= 'OFFSET ' . ((int) $this->_offset) . ' ';
		}

		if (!empty($this->_options))
		{
			$options = array();
			foreach ($this->_options as $option)
			{
				$options[] = $this->quoteIdentifier($option['name']) . ' = ' . $this->quote($option['value']);
			}

			$query .= 'OPTION ' . implode(', ', $options);
		}

		$this->_last_compiled = $query;

		return $this;
	}


	/**
	 * Returns the latest compiled query
	 * 
	 * @return type
	 */
	public function get_compiled()
	{
		return $this->_last_compiled;
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
		$this->_type	 = 'select';
		$this->_select	 = \func_get_args();
		return $this;
	}


	/**
	 * 
	 * 
	 * @param type $array
	 * @return \Foolz\Sphinxql\Sphinql
	 */
	public function from($array = null)
	{
		if (is_string($array))
		{
			$this->_from = \func_get_args();
		}

		if (is_array($array))
		{
			$this->_from = $array;
		}

		return $this;
	}


	public function match($column, $value, $half = false)
	{
		$this->_match[] = array('column' => $column, 'value' => $value, 'half'	=> $half);
		return $this;
	}


	public function where($column, $operator, $value = null, $or = false)
	{
		if ($value === null)
		{
			$value		 = $operator;
			$operator	 = '=';
		}

		$this->_where[] = array(
			'ext_operator'	 => $or ? 'OR' : 'AND',
			'column'		 => $column,
			'operator'		 => $operator,
			'value'			 => $value
		);
		return $this;
	}


	public function or_where($column, $operator, $value = null)
	{
		$this->where($column, $operator, $value, true);
		return $this;
	}


	public function where_open()
	{
		$this->_where[] = array('ext_operator' => 'AND (');
		return $this;
	}


	public function or_where_open()
	{
		$this->_where[] = array('ext_operator' => 'OR (');
		return $this;
	}


	public function where_close()
	{
		$this->_where[] = array('ext_operator' => ')');
		return $this;
	}


	public function group_by($column)
	{
		$this->_group_by[] = $column;
		return $this;
	}


	public function order_by($column, $direction = null)
	{
		$this->_order_by[] = array('column'	 => $column, 'direction'	 => $direction);
		return $this;
	}


	public function limit($offset, $limit = null)
	{
		if ($limit === null)
		{
			$this->_limit = (int) $offset;
		}

		$this->offset($offset);
		$this->_limit = (int) $limit;

		return $this;
	}


	public function offset($offset)
	{
		$this->_offset = (int) $offset;
		return $this;
	}


	public function option($name, $value)
	{
		$this->_options[] = array('name'	 => $name, 'value'	 => $value);
		return $this;
	}


	public function escapeString($string, $decode = FALSE)
	{
		$from = array('\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '=');
		$to = array('\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\=');
		return str_replace($from, $to, $string);
	}


	public function halfEscapeString($string, $decode = FALSE)
	{
		$from = array('\\', '(', ')', '!', '@', '~', '&', '/', '^', '$', '=');
		$to = array('\\\\', '\(', '\)', '\!', '\@', '\~', '\&', '\/', '\^', '\$', '\=');
		$string	 = str_replace($from, $to, $string);
		$string	 = preg_replace("'\"([^\s]+)-([^\s]*)\"'", "\\1\-\\2", $string);
		return preg_replace("'([^\s]+)-([^\s]*)'", "\"\\1\-\\2\"", $string);
	}

}