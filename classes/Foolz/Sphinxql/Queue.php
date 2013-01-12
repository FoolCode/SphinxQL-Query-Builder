<?php

namespace Foolz\SphinxQL;

/**
 * Extends the SphinxQL connection class utilizing the MySQLi::multiQuery() function.
 */
class Queue extends Connection
{
	/**
	 * Array of added Sphinxql objects
	 *
	 * @var  \Foolz\Sphinxql\Sphinxql[]
	 */
	protected $queue = array();

	/**
	 * Array of compiled queries
	 *
	 * @var  string[]
	 */
	protected $compiled = array();

	/**
	 * Add a Sphinxql query builder object to the queue
	 *
	 * @param  \Foolz\Sphinxql\Sphinxql  $sphinxql
	 *
	 * @return  \Foolz\Sphinxql\Queue  The current object
	 */
	public function add(\Foolz\Sphinxql\Sphinxql $sphinxql)
	{
		$this->queue[] = $sphinxql;

		return $this;
	}

	/**
	 * Runs all the queries with mysqli::multi_query(). It will use the connection of the first object loaded.
	 *
	 * @return  array  The result array
	 */
	public function execute()
	{
		foreach ($this->queue as $sphinxql)
		{
			$this->compiled[] = $sphinxql->compile()->getCompiled();
		}

		return static::multiQuery($this->compiled);
	}

	/**
	 * Sends multiple queries to Sphinx
	 *
	 * @param  string[]  $query  Array of queries in string form
	 *
	 * @return  array  The result array
	 * @throws  \Foolz\Sphinxql\SphinxqlException          If the input array is empty
	 * @throws  \Foolz\Sphinxql\SphinxqlDatabaseException  If a query generated an error when being executed
	 */
	public static function multiQuery(Array $query)
	{
		if (count($query) === 0)
		{
			throw new SphinxqlException('No query queued.');
		}

		static::getConnection() or static::connect();

		static::getConnection()->multi_query(implode(';', $query));

		if (static::getConnection()->error)
		{
			throw new SphinxqlDatabaseException('['.static::getConnection()->errno.'] '.
				static::getConnection()->error.' [ '.implode(';', $query).']');
		}

		$multi_result = array();
		$multi_count = 0;

		do
		{
			if ($resource = static::getConnection()->store_result())
			{
				$multi_result[$multi_count] = array();

				while ($row = $resource->fetch_assoc())
				{
					$multi_result[$multi_count][] = $row;
				}

				$resource->free_result();
			}

			$continue = false;
			if (static::getConnection()->more_results())
			{
				$multi_count++;
				static::getConnection()->next_result();
				$continue = true;
			}
		} while ($continue);

		return $multi_result;
	}
}