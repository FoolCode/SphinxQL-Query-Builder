<?php

/**
 * Bootstrap for FuelPHP use only
 */

\Autoloader::add_classes(array(
	'Foolz\\Sphinxql\\Sphinxql' => __DIR__.'/classes/Foolz/Sphinxql/Sphinxql.php',
	'Foolz\\Sphinxql\\SphinxqlConnection' => __DIR__.'/classes/Foolz/Sphinxql/SphinxqlConnection.php',
	'Foolz\\Sphinxql\\SphinxqlExpression' => __DIR__.'/classes/Foolz/Sphinxql/SphinxqlExpression.php'
));