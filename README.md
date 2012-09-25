Query Builder for SphinxQL
==========================

### About

This is a PHP Query Builder created ad-hoc to work with SphinxQL, an SQL dialect to use with the Sphinx search engine. 
It maps every function listed in the [SphinxQL reference](http://sphinxsearch.com/docs/current.html#sphinxql-reference) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-sphinxql-benchmark/) than the Sphinx API, beside having more functions.

This Query Builder has no dependencies except PHP 5.3, `\MySQLi` and of course a working Sphinx server. FuelPHP is not necessary but we've added a bootstrap for using it as a Package. It is styled after FuelPHP's Query Builder.

__This package is BETA QUALITY.__ Don't rely on it in production unless you tested it massively in development.

### Code Quality

Most of the methods in the package are unit tested. Methods that haven't been tested are single queries like `flushRtIndex`, but as they are independent they are supposed to work.

We test on Travis-CI with the SVN build of Sphinx: [![Build Status](https://secure.travis-ci.org/FoolRulez/fuel-sphinxql.png)](http://travis-ci.org/FoolRulez/fuel-sphinxql)

## Usage

The examples will omit the namespace.

	use Foolz\Sphinxql\Sphinxql as Sphinxql;

	// if you don't use the Sphinxql default connection, use this function to change the host and port
	Sphinxql::addConnection('superspecial', 'yourhost.com', 9231);
	Sphinxql::setConnection('superspecial');
	
	$query = Sphinxql::select('column_one', 'column_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();


#### General

The static connection manager lets you handle multiple connections.

There's the `default` connection, that connects to 127.0.0.1:9306 as per SphinxQL defaults.

* __Sphinxql::silenceConnectionWarning($enable = true)__
	
	Use it when you have warning display enabled in PHP, but you don't want to see errors when MySQLi fails connecting to the server. Custom errors are in place. (This is actually the so-evil @ silencing. Use it if you know what are you doing.)

	_Disabled by default._

* __Sphinxql::addConnection($name = 'default', $host = '127.0.0.1', $port = 9306)__

	Use it to add connection to the array of available connections.

* __Sphinxql::setConnection($name)__

	Set the connection to be used for the next operations. Remember that the class always starts with `default` set.

* __Sphinxql::getConnectionInfo($name = null)__

	Get info (host, port) on the connection. When name is not specified it gives info on the currently selected connection.

* __Sphinxql::connect()__

	_Throws \Foolz\Sphinxql\SphinxqlConnectionException_

	Enstablish the connection to the server.

* __Sphinxql::getConnection()__

	_Throws \Foolz\Sphinxql\SphinxqlConnectionException_

	Returns the \MySQLi object of the currently selected connection, an exception if not available.

* __Sphinxql::query($query)__

	Runs the query. Returns an array of results on `SELECT`, or an array with the number of affected rows (Sphinx doesn't support last-insert-id, so this values for `INSERT` too).


#### Getting around escaping

Often you need to run SQL functions, but those would get escaped as other values or identifiers. You can ignore the escaping by wrapping the query in a SphinxqlExpression.

* __Sphinxql::expr($string)__

	Disables escaping for the string.


#### SELECT, INSERT, REPLACE, UPDATE, DELETE

Each of these can be called statically or non-statically. It follows SQL logic.

	$query = Sphinxql::select('column', 'anothercolumn')->from('anindex', 'anotherindex');
	$query = Sphinxql::insert()->into('oneindex');
	$query = Sphinxql::replace()->into('oneindex');
	$query = Sphinxql::update('oneindex')
	$query = Sphinxql::delete()->from('oneindex')


#### Where

Classic WHERE, works with Sphinx filters and fulltext. 

    $sq->where('column', 'value');
    // WHERE `column` = 'value'

    $sq->where('column', '=', 'value');
    // WHERE `column` = 'value'

    $sq->where('column', '>=', 'value')
    // WHERE `column` >= 'value'

    $sq->where('column', 'IN', array('value1', 'value2', 'value3'));
    // WHERE `column` IN ('value1', 'value2', 'value3')

    $sq->where('column', 'BETWEEN', array('value1', 'value2'))
    // WHERE `column` BETWEEN 'value1' AND 'value2'
    // WHERE `example` BETWEEN 10 AND 100

_While implemented in the package, `OR` and parenthesis are not yet implemented in SphinxQL_.


#### Match

* __$sq->match($column, $value, $half = false)__

	Search in full-text fields. Can be used multiple times in the same query.

		$sq->match('title', 'Otoshimono')
			->match('character', 'Nymph');

	The characters are fully escaped. You will need to use Sphinxql::expr($value) to use your own options. 
	
	The `$half`, if turned to `true`, will allow the following characters: `-`, `|`, `"`. You __will have to__ wrap the query in a `try` if you use this feature and expose it to public interfaces, because character order might throw a query error.

		try
		{
			$result Sphinxql::select()
				->from('rt')
				->match('title', 'Sora no || Otoshimono')
				->execute();
		}
		catch (\Foolz\Sphinxql\SphinxqlDatabaseException $e)
		{
			// it will get here because two `|` one after the other aren't allowed
		}

#### Grouping, ordering etc.
 
* __$sq->groupBy($column)__

	`GROUP BY $column`

* __$sq->withinGroupOrderBy($column, $direction = null)__

	`WITHIN GROUP ORDER BY $column [$direction]`

	Direction can be omitted with `null`, or be `asc` or `desc` case insensitive.

* __$sq->orderBy($column, $direction = null)__

	`ORDER BY $column [$direction]`

	Direction can be omitted with `null`, or be `asc` or `desc` case insensitive.

* __$sq->offset($offset)__

	`LIMIT $offset, 9999999999999`

	Set the offset. The `LIMIT` is set to a high number because SphinxQL doesn't support the `OFFSET` keyword.

* __$sq->limit($limit)__

	`LIMIT $limit`

* __$sq->limit($offset, $limit)__

	`LIMIT $offset, $limit`

* __$sq->option($name, $value)__

	`OPTION $name = $value`

	Set a SphinxQL option like `max_matches` or `reverse_scan` for this query only.

#### Escaping

* __$sq->escape($value)__

	Returns the escaped value, processed with `\MySQLi::real_escape_string`.

* __$sq->quoteIdentifier($value)__

	Adds oblique quotes to identifiers.
