<?php

/**
 * Bootstrap for FuelPHP use only
 */

\Autoloader::add_classes(array(
	'Foolz\\Sphinxql\\Sphinxql' => __DIR__.'/classes/Sphinxql.php',
	'Foolz\\Sphinxql\\SphinxqlConnection' => __DIR__.'/classes/SphinxqlConnection.php',
	'Foolz\\Sphinxql\\SphinxqlExpression' => __DIR__.'/classes/SphinxqlExpression.php'
));