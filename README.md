Query Builder for SphinxQL
==========================

## About

This is a SphinxQL Query Builder used to work with SphinxQL, a SQL dialect used with the Sphinx search engine. It maps every function listed in the [SphinxQL reference](http://sphinxsearch.com/docs/current.html#SphinxQL-reference) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-SphinxQL-benchmark/) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-SphinxQL-benchmark/) than the available Sphinx API.

This Query Builder has no dependencies besides PHP 5.3, `\MySQLi` extension, and [Sphinx](http://sphinxsearch.com).

__This package is BETA QUALITY.__ It is recommended that you do extensive testing in development before using it in a production environment.

## Code Quality

The majority of the methods in the package have been unit tested. The only methods that have not been tested are single queries such as `flushRtIndex`, but these are independent and should work fine.

We have tested our package locally and remotely with Travis-CI:

[![Build Status](https://travis-ci.org/FoolCode/SphinxQL-Query-Builder.png)](https://travis-ci.org/FoolCode/SphinxQL-Query-Builder)


## Installation

This is a Composer package. You can install this package with the following command: `php composer.phar install`


## Usage

The following examples will omit the namespace.

	use Foolz\SphinxQL\SphinxQL;
	use Foolz\SphinxQL\Connection;

	// create a SphinxQL Connection object to use with SphinxQL
	$conn = new Connection();
	$conn->setConnectionParams('domain.tld', 9306);

	// use SphinxQL::forge($conn) to initialize and bind the connection to be used for future calls
	SphinxQL::forge($conn);

	$query = SphinxQL::forge()->select('column_one', 'colume_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();


#### Connection

* __$conn = new Connection()__

	Create a new Connection instance to use what follows.

* __$conn->silenceConnectionWarning($enable = true)__

	Forces any warnings and errors displayed by the `\MySQLi` extension upon connection failure to be suppressed.

	_This is disabled by default._

* __$conn->setConnectionParams($host = '127.0.0.1', $port = 9306)__

	Sets the connection parameters used to establish a connection to the server.

* __$conn->query($query)__

	Performs the query on the server. Returns an _array_ of results for `SELECT`, or an _int_ with the number of rows affected.

_More methods are available in the Connection class, but usually not necessary as these are handled automatically._

#### Bypass Query Escaping

Often, you would need to call and run SQL functions that shouldn't be escaped in the query. You can bypass the query escape by wrapping the query in an `\Expression`.

* __SphinxQL::expr($string)__

	Returns the string without being escaped.

#### Query Escaping

There are cases when an input __must__ be escaped in the SQL statement. The following functions are used to handle any escaping required for the query.

* __$sq->escape($value)__

	Returns the escaped value. This is processed with the `\MySQLi::real_escape_string()` function.

* __$sq->quoteIdentifier($identifier)__

	Adds backtick quotes to the identifier. For array elements, use `$sq->quoteIdentifierArray($arr)`.

* __$sq->quote($value)__

	Adds quotes to the value and escapes it. For array elements, use `$sq->quoteArr($arr)`.

* __$sq->escapeMatch($value)__

	Escapes the string to be used in `MATCH`.

* __$sq->halfEscapeMatch($value)__

	Escapes the string to be used in `MATCH`. The following characters are allowed: `-`, `|`, and `"`.
	_Refer to `$sq->match()` for more information._


#### SET VARIABLE

* __SphinxQL::forge()->setVariable($name, $value, $global = false)__

	Sets a variable server-side.


#### SHOW

* `SphinxQL::forge()->meta() => 'SHOW META'`
* `SphinxQL::forge()->warnings() => 'SHOW WARNINGS'`
* `SphinxQL::forge()->status() => 'SHOW STATUS'`
* `SphinxQL::forge()->tables() => 'SHOW TABLES'`
* `SphinxQL::forge()->variables() => 'SHOW VARIABLES'`
* `SphinxQL::forge()->variablesSession() => 'SHOW SESSION VARIABLES'`
* `SphinxQL::forge()->variablesGlobal() => 'SHOW GLOBAL VARIABLES'`


#### SELECT

* __$sq = SphinxQL::forge()->select($column1, $column2, ...)->from($index1, $index2, ...)__

	Begins a `SELECT` query statement. If no column is specified, the statement defaults to using `*`. Both `$column1` and `$index1` can be arrays.


#### INSERT, REPLACE

This will return an `INT` with the number of rows affected.

* __$sq = SphinxQL::forge()->insert()->into($index)__

	Begins an `INSERT`.

* __$sq = SphinxQL::forge()->replace()->into($index)__

	Begins an `REPLACE`.

* __$sq->set($associative_array)__

	Inserts an associative array, with the keys as the columns and values as the value for the respective column.

* __$sq->value($column1, $value1)->value($column2, $value2)->value($column3, $value3)__

	Sets the value of each column individually.

* __$sq->columns($column1, $column2, $column3)->values($value1, $value2, $value3)->values($value11, $value22, $value33)__

	Allows the insertion of multiple arrays of values in the specified columns.

	Both `$column1` and `$index1` can be arrays.


#### UPDATE

This will return an `INT` with the number of rows affected.

* __$sq = SphinxQL::forge()->update($index)__

	Begins an `UPDATE`.

* __$sq->value($column1, $value1)->value($column2, $value2)__

	Updates the selected columns with the respective value.

* __$sq->set($associative_array)__

	Inserts the associative array, where the keys are the columns and the respective values are the column values.


#### DELETE

Will return an array with an `INT` as first member, the number of rows deleted.

* __$sq = SphinxQL::forge()->delete()->from($column)__

	Begins a `DELETE`.


#### WHERE

* __$sq->where($column, $operator, $value)__

	Standard WHERE, extended to work with Sphinx filters and full-text.

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


#### MATCH

* __$sq->match($column, $value, $half = false)__

	Search in full-text fields. Can be used multiple times in the same query.

		$sq->match('title', 'Otoshimono')
			->match('character', 'Nymph');

	By default, all inputs are fully escaped. The usage of `SphinxQL::expr($value)` is required to bypass the statement escapes.

	The `$half` argument, if set to `true`, will not escape and allow the usage of the following characters: `-`, `|`, `"`. If you plan to use this feature and expose it to public interfaces, it is __recommended__ that you wrap the query in a `try catch` block as the character order may `throw` a query error.

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


#### GROUP, WITHIN GROUP, ORDER, OFFSET, LIMIT, OPTION

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


#### TRANSACTION

* __SphinxQL::forge()->transactionBegin()__

	Begins a transaction.

* __SphinxQL::forge()->transactionCommit()__

	Commits a transaction.

* __SphinxQL::forge()->transactionRollback()__

	Rollbacks a transaction.


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

#### Multi-Query

* __$sq->enqueue()__

	Queues the query.

* __$sq->executeBatch()__

	Returns an array of the results of all the queued queries.

#### More

There's several more functions to complete the SphinxQL library:

* `SphinxQL::forge()->callSnippets($data, $index, $extra = array())`
* `SphinxQL::forge()->callKeywords($text, $index, $hits = null)`
* `SphinxQL::forge()->describe($index)`
* `SphinxQL::forge()->createFunction($udf_name, $returns, $soname)`
* `SphinxQL::forge()->dropFunction($udf_name)`
* `SphinxQL::forge()->attachIndex($disk_index, $rt_index)`
* `SphinxQL::forge()->flushRtIndex($index)`