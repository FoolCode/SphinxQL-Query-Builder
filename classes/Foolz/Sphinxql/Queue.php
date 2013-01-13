<?php

namespace Foolz\SphinxQL;

/**
 * Extends the SphinxQL connection class utilizing the MySQLi::multiQuery() function.
 */
class Queue extends Connection
{
	/**
	 * Array of added SphinxQL objects
	 *
	 * @var  \Foolz\SphinxQL\SphinxQL[]
	 */
	protected $queue = array();

	/**
	 * Array of compiled queries
	 *
	 * @var  string[]
	 */
	protected $compiled = array();

	/**
	 * Add a SphinxQL query builder object to the queue
	 *
	 * @param  \Foolz\SphinxQL\SphinxQL  $sphinxql
	 *
	 * @return  \Foolz\SphinxQL\Queue  The current object
	 */
	public function add(\Foolz\SphinxQL\SphinxQL $sphinxql)
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

		return $this->multiQuery($this->compiled);
	}

	/**
	 * Sends multiple queries to Sphinx
	 *
	 * @param  string[]  $query  Array of queries in string form
	 *
	 * @return  array  The result array
	 * @throws  \Foolz\SphinxQL\SphinxException          If the input array is empty
	 * @throws  \Foolz\SphinxQL\SphinxDatabaseException  If a query generated an error when being executed
	 */
	public function multiQuery(Array $query)
	{
		if (count($query) === 0)
		{
			throw new SphinxException('No query queued.');
		}

		$this->getConnection() or $this->connect();

		$this->getConnection()->multi_query(implode(';', $query));

		if ($this->getConnection()->error)
		{
			throw new SphinxDatabaseException('['.$this->getConnection()->errno.'] '.
				$this->getConnection()->error.' [ '.implode(';', $query).']');
		}

		$multi_result = array();
		$multi_count = 0;

		do
		{
			if ($resource = $this->getConnection()->store_result())
			{
				$multi_result[$multi_count] = array();

				while ($row = $resource->fetch_assoc())
				{
					$multi_result[$multi_count][] = $row;
				}

				$resource->free_result();
			}

			$continue = false;
			if ($this->getConnection()->more_results())
			{
				$multi_count++;
				$this->getConnection()->next_result();
				$continue = true;
			}
		} while ($continue);

		return $multi_result;
	}
}