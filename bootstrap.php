<?php

/**
 * Bootstrap for FuelPHP use only
 */

\Autoloader::add_classes(array(
	'Foolz\\Sphinxql\\Sphinxql' => __DIR__.'/classes/Sphinxql.php',
	'Foolz\\Sphinxql\\SphinxqlConnection' => __DIR__.'/classes/SphinxqlConnection.php',
	'Foolz\\Sphinxql\\SphinxqlExpression' => __DIR__.'/classes/SphinxqlExpression.php'
));

$sphinxql = \Foolz\Sphinxql\Sphinxql::forge('xengi.org',9313);

$sphinxql->select()
	->from('a_ancient')
	->match('comment', 'wox*', true)
	->where('subnum', '=', 0)
	->option('max_matches', 5000)
	->option('reverse_scan', 1);

$sphinxql->execute();
\Debug::dump($sphinxql->get_result());


die($sphinxql->get_compiled());