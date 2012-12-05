<?php

namespace Foolz\Sphinxql;

class Queue extends Connection
{
	/**
	 * The array of added Sphinxql objects
	 *
	 * @var  \Foolz\Sphinxql\Sphinxql[]
	 */
	protected $queue = array();

	/**
	 * The array of
	 *
	 * @var  string[]
	 */
	protected $compiled = array();

	/**
	 *
	 *
	 * @param  \Foolz\Sphinxql\Sphinxql  $sphinxql
	 *
	 * @return  \Foolz\Sphinxql\Queue
	 */
	public function add(\Foolz\Sphinxql\Sphinxql $sphinxql)
	{
		$this->queue[] = $sphinxql;

		return $this;
	}

	/**
	 * Runs all the queries with mysqli::multi_query. It will use the connection of the first object loaded.
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
	 * @param  array  $query
	 *
	 * @return  array
	 * @throws  SphinxqlDatabaseException
	 */
	public static function multiQuery(array $query)
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