<?php

/**
 * Bootstrap for FuelPHP use only
 */

\Autoloader::add_classes(array(
	'Foolz\\Sphinxql\\Sphinxql' => __DIR__.'/classes/Foolz/Sphinxql/Sphinxql.php',
	'Foolz\\Sphinxql\\Connection' => __DIR__.'/classes/Foolz/Sphinxql/Connection.php',
	'Foolz\\Sphinxql\\Expression' => __DIR__.'/classes/Foolz/Sphinxql/Expression.php',
	'Foolz\\Sphinxql\\Queue' => __DIR__.'/classes/Foolz/Sphinxql/Queue.php'
));