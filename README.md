Query Builder for SphinxQL
==========================

## About

This is a SphinxQL Query Builder used to work with SphinxQL, a SQL dialect used with the Sphinx search engine. It maps most of the functions listed in the [SphinxQL reference](http://sphinxsearch.com/docs/current.html#SphinxQL-reference) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-SphinxQL-benchmark/) than the available Sphinx API.

This Query Builder has no dependencies except PHP 5.3, `\MySQLi` extension, and [Sphinx](http://sphinxsearch.com). It is also compatible with [HHVM](http://hhvm.com).

__This package is BETA QUALITY.__ It is recommended that you do extensive testing in development before using it in a production environment.

### Missing methods?

SphinxQL evolves very fast.

Most of the new functions are static one liners like `SHOW PLUGINS`. We'll avoid trying to keep up with these methods, as they are easy to just call directly (`SphinxQL::create($conn)->query($sql)->execute()`). You're free to submit pull requests to support these methods.

If any feature is unreachable through this library, open a new issue or send a pull request.

## Code Quality

The majority of the methods in the package have been unit tested. The unit tests are run both in PHP and HHVM.

The only methods that have not been fully tested are the Helpers, which are mostly simple shorthands for SQL strings.

We test our package locally and remotely with Travis-CI:

[![Build Status](https://travis-ci.org/FoolCode/SphinxQL-Query-Builder.png)](https://travis-ci.org/FoolCode/SphinxQL-Query-Builder)

## How to Contribute

### Pull Requests

1. Fork the SphinxQL Query Builder repository
2. Create a new branch for each feature or improvement
3. Submit a pull request from each branch to the **master** branch

It is very important to separate new features or improvements into separate feature branches, and to send a pull
request for each branch. This allows me to review and pull in new features or improvements individually.

### Style Guide

All pull requests must adhere to the [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) standard.

### Unit Testing

All pull requests must be accompanied by passing unit tests and complete code coverage. The SphinxQL Query Builder uses
`phpunit` for testing.

[Learn about PHPUnit](https://github.com/sebastianbergmann/phpunit/)

## Installation

This is a Composer package. You can install this package with the following command: `php composer.phar install`

## Usage

The following examples will omit the namespace.

```php
<?php
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Connection;

// create a SphinxQL Connection object to use with SphinxQL
$conn = new Connection();
$conn->setParams(array('host' => 'domain.tld', 'port' => 9306));

$query = SphinxQL::create($conn)->select('column_one', 'colume_two')
    ->from('index_ancient', 'index_main', 'index_delta')
    ->match('comment', 'my opinion is superior to yours')
    ->where('banned', '=', 1);

$result = $query->execute();
```

### Connection

* __$conn = new Connection()__

	Create a new Connection instance to be used with the following methods or SphinxQL class.

* __$conn->silenceConnectionWarning($enable = true)__

	Suppresses any warnings and errors displayed by the `\MySQLi` extension upon connection failure.
	_This is disabled by default._

* __$conn->setParams($params = array('host' => '127.0.0.1', 'port' => 9306))__

	Sets the connection parameters used to establish a connection to the server. Supported parameters: 'host', 'port', 'socket', 'options'.

* __$conn->query($query)__

	Performs the query on the server. Returns an _array_ of results for `SELECT`, or an _int_ with the number of rows affected.

_More methods are available in the Connection class, but usually not necessary as these are handled automatically._

### SphinxQL

* __SphinxQL::create($conn)__

	Creates a SphinxQL instance used for generating queries.

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

#### SELECT

* __$sq = SphinxQL::create($conn)->select($column1, $column2, ...)->from($index1, $index2, ...)__

	Begins a `SELECT` query statement. If no column is specified, the statement defaults to using `*`. Both `$column1` and `$index1` can be arrays.

#### INSERT, REPLACE

This will return an `INT` with the number of rows affected.

* __$sq = SphinxQL::create($conn)->insert()->into($index)__

	Begins an `INSERT`.

* __$sq = SphinxQL::create($conn)->replace()->into($index)__

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

* __$sq = SphinxQL::create($conn)->update($index)__

	Begins an `UPDATE`.

* __$sq->value($column1, $value1)->value($column2, $value2)__

	Updates the selected columns with the respective value.

* __$sq->set($associative_array)__

	Inserts the associative array, where the keys are the columns and the respective values are the column values.

#### DELETE

Will return an array with an `INT` as first member, the number of rows deleted.

* __$sq = SphinxQL::create($conn)->delete()->from($index)->where(...)__

	Begins a `DELETE`.

#### WHERE

* __$sq->where($column, $operator, $value)__

	Standard WHERE, extended to work with Sphinx filters and full-text.

    ```php
    <?php
    // WHERE `column` = 'value'
    $sq->where('column', 'value');

    // WHERE `column` = 'value'
    $sq->where('column', '=', 'value');

    // WHERE `column` >= 'value'
    $sq->where('column', '>=', 'value')

    // WHERE `column` IN ('value1', 'value2', 'value3')
    $sq->where('column', 'IN', array('value1', 'value2', 'value3'));

    // WHERE `column` NOT IN ('value1', 'value2', 'value3')
    $sq->where('column', 'NOT IN', array('value1', 'value2', 'value3'));

    // WHERE `column` BETWEEN 'value1' AND 'value2'
    // WHERE `example` BETWEEN 10 AND 100
    $sq->where('column', 'BETWEEN', array('value1', 'value2'))
	```

	_It should be noted that `OR` and parenthesis are not supported and implemented in the SphinxQL dialect yet._

#### MATCH

* __$sq->match($column, $value, $half = false)__

	Search in full-text fields. Can be used multiple times in the same query. Column can be an array. Value can be an Expression to bypass escaping (and use your own custom solution).

    ```php
    <?php
    $sq->match('title', 'Otoshimono')
        ->match('character', 'Nymph')
        ->match(array('hates', 'despises'), 'Oregano');
    ```

	By default, all inputs are escaped. The usage of `SphinxQL::expr($value)` is required to bypass the default escaping and quoting function.

	The `$half` argument, if set to `true`, will not escape and allow the usage of the following characters: `-`, `|`, `"`. If you plan to use this feature and expose it to public interfaces, it is __recommended__ that you wrap the query in a `try catch` block as the character order may `throw` a query error.

    ```php
    <?php
    try
    {
        $result = SphinxQL::create($conn)->select()
            ->from('rt')
            ->match('title', 'Sora no || Otoshimono', true)
            ->match('title', SphinxQL::expr('"Otoshimono"/3'))
            ->match('loves', SphinxQL::expr(custom_escaping_fn('(you | me)')));
            ->execute();
    }
    catch (\Foolz\SphinxQL\DatabaseException $e)
    {
        // an error is thrown because two `|` one after the other aren't allowed
    }
	```

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

* __SphinxQL::create($conn)->transactionBegin()__

	Begins a transaction.

* __SphinxQL::create($conn)->transactionCommit()__

	Commits a transaction.

* __SphinxQL::create($conn)->transactionRollback()__

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

* __$sq->enqueue(SphinxQL $next = null)__

	Queues the query. If a $next is provided, $next is appended and returned, otherwise a new SphinxQL object is returned.

* __$sq->executeBatch()__

	Returns an array of the results of all the queued queries.

```php
<?php
$result = SphinxQL::create($this->conn)
    ->select()
    ->from('rt')
    ->match('title', 'sora')
    ->enqueue(SphinxQL::create($this->conn)->query('SHOW META')) // this returns the object with SHOW META query
    ->enqueue() // this returns a new object
    ->select()
    ->from('rt')
    ->match('content', 'nymph')
    ->executeBatch();
```

`$result[0]` will contain the first select. `$result[1]` will contain the META for the first query. `$result[2]` will contain the second select.

### Helper

The `Helper` class contains useful methods that don't need "query building".

Remember to `->execute()` to get a result.

* __Helper::pairsToAssoc($result)__

	Takes the pairs from a SHOW command and returns an associative array key=>value

The following methods return a prepared `SphinxQL` object. You can also use `->enqueue($next_object)`:

```php
<?php
$result = SphinxQL::create($this->conn)
    ->select()
    ->from('rt')
    ->where('gid', 9003)
    ->enqueue(Helper::create($this->conn)->showMeta()) // this returns the object with SHOW META query prepared
    ->enqueue() // this returns a new object
    ->select()
    ->from('rt')
    ->where('gid', 201)
    ->executeBatch();
```

* `Helper::create($conn)->showMeta() => 'SHOW META'`
* `Helper::create($conn)->showWarnings() => 'SHOW WARNINGS'`
* `Helper::create($conn)->showStatus() => 'SHOW STATUS'`
* `Helper::create($conn)->shotTables() => 'SHOW TABLES'`
* `Helper::create($conn)->showVariables() => 'SHOW VARIABLES'`
* `Helper::create($conn)->setVariable($name, $value, $global = false)`
* `Helper::create($conn)->callSnippets($data, $index, $query, $options = array())`
* `Helper::create($conn)->callKeywords($text, $index, $hits = null)`
* `Helper::create($conn)->describe($index)`
* `Helper::create($conn)->createFunction($udf_name, $returns, $soname)`
* `Helper::create($conn)->dropFunction($udf_name)`
* `Helper::create($conn)->attachIndex($disk_index, $rt_index)`
* `Helper::create($conn)->flushRtIndex($index)`
* `Helper::create($conn)->optimizeIndex($index)`
* `Helper::create($conn)->showIndexStatus($index)`
* `Helper::create($conn)->flushRamchunk($index)`
