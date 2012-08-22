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

	// if you don't use the Sphinxql default connection, use this function to change the host and port
	Sphinxql::addConnection('superspecial', 'yourhost.com', 9231);
	Sphinxql::setConnection('superspecial');
	
	$query = Sphinxql::select('column_one', 'column_two')
		->from('index_delta', 'index_main', 'index_ancient')
		->match('comment', 'my opinion is better')
		->where('banned', '=', 1);

	$result = $query->execute();

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

