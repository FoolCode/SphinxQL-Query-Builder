<?php

/**
 * Bootstrap for FuelPHP use only
 */

\Autoloader::add_classes(array(
	'Foolz\\SphinxQL\\SphinxQL' => __DIR__.'/classes/Foolz/SphinxQL/Sphinxql.php',
	'Foolz\\SphinxQL\\Connection' => __DIR__.'/classes/Foolz/SphinxQL/Connection.php',
	'Foolz\\SphinxQL\\ConnectionPool' => __DIR__.'/classes/Foolz/SphinxQL/ConnectionPool.php',
	'Foolz\\SphinxQL\\Expression' => __DIR__.'/classes/Foolz/SphinxQL/Expression.php',
	'Foolz\\SphinxQL\\Queue' => __DIR__.'/classes/Foolz/SphinxQL/Queue.php'
));