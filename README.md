Query Builder for SphinxQL
==========================

### About

This is a PHP Query Builder created ad-hoc to work with SphinxQL, an SQL dialect to use with the Sphinx search engine. 
It maps every function listed in the [SphinxQL reference](http://sphinxsearch.com/docs/current.html#sphinxql-reference) and is generally [faster](http://sphinxsearch.com/blog/2010/04/25/sphinxapi-vs-sphinxql-benchmark/) than the Sphinx API, beside having more functions.

This Query Builder has no dependencies except PHP 5.3, `\MySQLi` and of course a working Sphinx server. FuelPHP is not necessary but we've added a bootstrap for using it as a Package. It is styled after FuelPHP's Query Builder.

__This package is ALPHA QUALITY.__

## Usage

The examples will omit the namespace.

	use Foolz\Sphinxql\Sphinxql as Sphinxql;

#### Static uses one default connection

If you have one single connection, it's convenient to use the static methods.

	// if you don't use the default connection, use this function to change the defaults
	Sphinxql::setDefault(array('host' => 'yourhost.com', 'port' => 9348, 'charset' => 'utf8));
	$query = Sphinxql::select('column_one', 'column_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();

To use it non-statically, the following `$sq = Sphinxql::forgeFromDefault();` will return the object.

#### Non-static uses custom connection

This is to be used when there's multiple servers to connect to. You can use it in conjunction with the static version.

	$sq = Sphinxql::forge('yourhost.com', 9348, 'charset' = 'utf8)
	$query = $sq->select('column_one', 'column_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();

Unlike with the static version, you will need to keep the `$sq` object to keep using the connection.

## Methods

#### SELECT, INSERT, REPLACE, UPDATE, DELETE

Each of these can be called statically or non-statically. It follows SQL logic.

	$query = Sphinxql::select('column', 'anothercolumn')->from('anindex', 'anotherindex');
	$query = Sphinxql::insert()->into('oneindex');
	$query = Sphinxql::replace()->into('oneindex');
	$query = Sphinxql::update('oneindex')
	$query = Sphinxql::delete()->from('oneindex')

#### Where

Classic WHERE, works with Sphinx filters and fulltext. _`OR` is not yet implemented in SphinxQL_.

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

