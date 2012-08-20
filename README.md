Query Builder for SphinxQL
==========================

### About

If you didn't know, the SphinxQL is faster than the API counterpart, and gives several more functions, especially for interacting with RT indexes.

This package maps every function listed in the [SphinxQL reference](http://sphinxsearch.com/docs/current.html#sphinxql-reference). It is closely styled to FuelPHP's query builder.

This package has no dependencies except PHP 5.3, `\MySQLi` and of course a working Sphinx server. FuelPHP is not necessary but we've added a bootstrap for using it as a Package.

__This package is ALPHA QUALITY.__ We use it into our FoolFrame project that is still unreleased. Try it at our own risk.

## Usage

The examples will use just \Sphinxql.

	use Foolz\Sphinxql\Sphinxql as Sphinxql;

#### Static uses one default connection

Since it the most of cases you will have one single connection, this likely will be enough for your needs.

	// if you don't use the default connection, use this function to change the defaults
	Sphinxql::setDefault(array('host' => 'yourhost.com', 'port' => 9348, 'charset' => 'utf8));
	$query = Sphinxql::select('column_one', 'column_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();

It connects at the first use you make of it, so when `Sphinxql::select()` is called.

If can also use this as non-static by calling `$sq = Sphinxql::forgeFromDefault();`.

#### Non-static uses custom connection

This is to be used when there's multiple servers to connect to.

	$sq = Sphinxql::forge('yourhost.com', 9348, 'charset' = 'utf8)
	$query = $sq->select('column_one', 'column_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();

It didn't change much, but you must keep a hold of that $sq object to be able to keep the object with the connection. Nothing weird there.

## Methods

#### SELECT, INSERT, REPLACE, UPDATE, DELETE

Each of these can be called statically or non-statically. It follows SQL logic.

	$query = Sphinxql::select('column', 'anothercolumn')->from('anindex', 'anotherindex');
	$query = Sphinxql::insert()->into('oneindex');
	$query = Sphinxql::replace()->into('oneindex');
	$query = Sphinxql::update('oneindex')
	$query = Sphinxql::delete()->from('oneindex')

#### Where

Classic WHERE, works with Sphinx filters and fulltext. ___OR__ is not yet implemented in SphinxQL_.

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

