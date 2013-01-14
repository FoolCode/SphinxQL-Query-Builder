Query Builder for SphinxQL
==========================

### About

This is a SphinxQL Query Builder used to work with SphinxQL, a SQL dialect used with the Sphinx search engine. It maps every function listed in the [Sphinx reference](http://sphinxsearch.com/docs/current.html#SphinxQL-reference) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-SphinxQL-benchmark/) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-SphinxQL-benchmark/) than the available Sphinx API.

This Query Builder has no dependencies besides PHP 5.3, `\MySQLi` extension, and [Sphinx](http://sphinxsearch.com).

__This package is BETA QUALITY.__ It is recommended that you do extensive testing in development before using it in a production environment.

### Code Quality

The majority of the methods in the package have been unit tested. The only methods that have not been tested are single queries such as `flushRtIndex`, but these are independent and should work fine.

We have tested our package locally and remotely with Travis-CI:

[![Build Status](https://secure.travis-ci.org/FoolRulez/fuel-SphinxQL.png)](http://travis-ci.org/FoolCode/SphinxQL-Query-Builder)

## Installation

This is a Composer package. You can install this package with the following command: `php composer.phar install`

## Usage

The examples will omit the namespace.

	use Foolz\SphinxQL\SphinxQL as SphinxQL;
	use Foolz\SphinxQL\Connection as SphinxConnection;

	// create a connection object to use for SphinxQL
	$conn = new SphinxConnection();
	$conn->setConnectionParams('domain.tld', 9306);

	// use SphinxQL::forge($conn) to initialize and bind the connection to be used
	SphinxQL::forge($conn);

	$query = SphinxQL::forge()->select('column_one', 'colume_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();


#### General

* __SphinxQL::forge()->silenceConnectionWarning($enable = true)__

	Use it when you have warning display enabled in PHP, but you don't want to see errors when MySQLi fails connecting to the server. Custom errors are in place. (This is actually the so-evil @ silencing. Use it if you know what are you doing.)

	_Disabled by default._

* __SphinxQL::forge()->addConnection($name = 'default', $host = '127.0.0.1', $port = 9306)__

	Use it to add connection to the array of available connections.

* __SphinxQL::forge()->setConnection($name)__

	Set the connection to be used for the next operations. Remember that the class always starts with `default` set.

* __SphinxQL::forge()->getConnectionInfo($name = null)__

	Get info (host, port) on the connection. When name is not specified it gives info on the currently selected connection.

* __SphinxQL::forge()->connect()__

	_Throws \Foolz\SphinxQL\SphinxQLConnectionException_

	Enstablish the connection to the server.

* __SphinxQL::forge()->getConnection()__

	_Throws \Foolz\SphinxQL\SphinxQLConnectionException_

	Returns the \MySQLi object of the currently selected connection, an exception if not available.

* __SphinxQL::forge()->ping()__

	Pings the server. Returns false on failure, true on success.

* __SphinxQL::forge()->close()__

	Closes the server connection.

* __SphinxQL::forge()->query($query)__

	Runs the query. Returns an array of results on `SELECT`, or an array with the number of affected rows (Sphinx doesn't support last-insert-id, so this values for `INSERT` too).


#### Bypass Query Escaping

Often, you would need to call and run SQL functions that shouldn't be escaped in the query. You can bypass the query escape by wrapping the query in an `\Expression`.

* __SphinxQL::expr($string)__

	Disables escaping for the string.


#### Executing and Compiling

* __$sq->execute()__

	Compiles, executes, and __returns__ an array of results of a query.

* __$sq->executeBatch()__

	Compiles, executes, and __returns__ an array of results for a multi-query.

* __$sq->compile()__

	Compiles the query.

* __$sq->getCompiled()__

	Returns the last query compiled.

* __$sq->getResult()__

	Returns the last result.

#### Select

* __$sq = SphinxQL::forge()->select($column1, $column2, ...)->from($index1, $index2, ...)__

	Begins a `SELECT` query statement. If no column is specified, the statement defaults to using `*`. Both `$column1` and `$index1` can be arrays.

#### Where

* $sq->where($column, $operator, $value)

	Classic WHERE, works with Sphinx filters and fulltext.

		// WHERE `column` = 'value'
		$sq->where('column', 'value');

		// WHERE `column` = 'value'
		$sq->where('column', '=', 'value');

		// WHERE `column` >= 'value'
		$sq->where('column', '>=', 'value')

		// WHERE `column` IN ('value1', 'value2', 'value3')
		$sq->where('column', 'IN', array('value1', 'value2', 'value3'));

		// WHERE `column` BETWEEN 'value1' AND 'value2'
		// WHERE `example` BETWEEN 10 AND 100
		$sq->where('column', 'BETWEEN', array('value1', 'value2'))

	_It should be noted that `OR` and parenthesis are not supported and implemented in the SphinxQL dialect yet._


#### Match

* __$sq->match($column, $value, $half = false)__

	Search in full-text fields. Can be used multiple times in the same query.

		$sq->match('title', 'Otoshimono')
			->match('character', 'Nymph');

	The characters are fully escaped. You will need to use SphinxQL::expr($value) to use your own options.

	The `$half`, if turned to `true`, will allow the following characters: `-`, `|`, `"`. You __will have to__ wrap the query in a `try` if you use this feature and expose it to public interfaces, because character order might throw a query error.

	The `$half` argument, if `true`, will allow the following charact
	The `$half` argument, if `true`, will not escape and allow the usage of the following characters: `-`, `|`, `"`. If you plan to use this feature and expose it to public interfaces, it is __recommended__ that you wrap the query in a `try catch` block as the character order may `throw` a query error.

		try
		{
			$result = SphinxQL::forge()->select()
				->from('rt')
				->match('title', 'Sora no || Otoshimono')
				->execute();
		}
		catch (\Foolz\SphinxQL\DatabaseException $e)
		{
			// an error is thrown because two `|` one after the other aren't allowed
		}

#### Grouping, Ordering, Offset, Limit, and Option

* __$sq->groupBy($column)__

	`GROUP BY $column`

* __$sq->withinGroupOrderBy($column, $direction = null)__

	`WITHIN GROUP ORDER BY $column [$direction]`

	Direction can be omitted with `null`, or be `ASC` or `DESC` case insensitive.

* __$sq->orderBy($column, $direction = null)__

	`ORDER BY $column [$direction]`

	Direction can be omitted with `null`, or be `ASC` or `DESC` case insensitive.

* __$sq->offset($offset)__

	`LIMIT $offset, 9999999999999`

	Set the offset. Since SphinxQL doesn't support the `OFFSET` keyword, `LIMIT` has been set at an extremely high number.

* __$sq->limit($limit)__

	`LIMIT $limit`

* __$sq->limit($offset, $limit)__

	`LIMIT $offset, $limit`

* __$sq->option($name, $value)__

	`OPTION $name = $value`

	Set a SphinxQL option such as `max_matches` or `reverse_scan` for the query.

#### Insert and Replace

This will return an `INT` with the number of rows affected.

* __$sq = SphinxQL::forge()->insert()->into($index)__

	Begins an `INSERT`.

* __$sq = SphinxQL::forge()->replace()->into($index)__

	Begins an `REPLACE`.

* __$sq->set($associative_array)__

	Inserts the associative array, where the keys are the columns and the respective values are the column values.

* __$sq->value($column1, $value1)->value($column2, $value2)->value($column3, $value3)__

	Sets columns one by one

* __$sq->columns($column1, $column2, $column3)->values($value1, $value2, $value3)->values($value11, $value22, $value33)__

	Allows inserting multiple arrays of values in the specified columns.

	`$column1` and `$value1` can be arrays.


#### Update

This will return an `INT` with the number of rows affected.

* __$sq = SphinxQL::forge()->update($index)__

	Begins an `UPDATE`.

* __$sq->value($column1, $value1)->value($column2, $value2)__

	Updates the selected columns with the respective value.

* __$sq->set($associative_array)__

	Inserts the associative array, where the keys are the columns and the respective values are the column values.

The `WHERE` part of the query works just as for `SELECT`.


#### Delete

Will return an array with an `INT` as first member, the number of rows deleted.

* __$sq = SphinxQL::forge()->delete()->from($column)__

	Begins a `DELETE`.

The `WHERE` part of the query works just as for `SELECT`.

#### Multi-Query

* __$sq->enqueue()__

	Queues the query.

* __$sq->executeBatch()__

	Returns an array of the results of all the queued queries.

#### Transactions

* __SphinxQL::forge()->transactionBegin()__

	Begins a transaction.

* __SphinxQL::forge()->transactionCommit()__

	Commits a transaction.

* __SphinxQL::forge()->transactionRollback()__

	Rollbacks a transaction.


#### Escaping

* __$sq->escape($value)__

	Returns the escaped value, processed with `\MySQLi::real_escape_string`.

* __$sq->quoteIdentifier($identifier)__

	Adds oblique quotes to identifiers. To run this on array elements use `$sq->quoteIdentifierArr($arr)`.

* __$sq->quote($value)__

	Adds quotes to values and escapes. To run this on array elements use `$sq->quoteArr($arr)`.

* __$sq->escapeMatch($value)__

	Escapes the string for use in a `MATCH`.

* __$sq->halfEscapeMatch($value)__

	Escapes the string for use in a `MATCH`. Allows `-`, `|`, `"`. Read about this on the `$sq->match()` explanation.


#### Show

	SphinxQL::forge()->meta() => 'SHOW META'
	SphinxQL::forge()->warnings() => 'SHOW WARNINGS'
	SphinxQL::forge()->status() => 'SHOW STATUS'
	SphinxQL::forge()->tables() => 'SHOW TABLES'
	SphinxQL::forge()->variables() => 'SHOW VARIABLES'
	SphinxQL::forge()->variablesSession() => 'SHOW SESSION VARIABLES'
	SphinxQL::forge()->variablesGlobal() => 'SHOW GLOBAL VARIABLES'


#### Set variable

* __SphinxQL::forge()->setVariable($name, $value, $global = false)__

	Set a server variable.


#### More

There's several more functions to complete the SphinxQL library:

* `SphinxQL::forge()->callSnippets($data, $index, $extra = array())`
* `SphinxQL::forge()->callKeywords($text, $index, $hits = null)`
* `SphinxQL::forge()->describe($index)`
* `SphinxQL::forge()->createFunction($udf_name, $returns, $soname)`
* `SphinxQL::forge()->dropFunction($udf_name)`
* `SphinxQL::forge()->attachIndex($disk_index, $rt_index)`
* `SphinxQL::forge()->flushRtIndex($index)`