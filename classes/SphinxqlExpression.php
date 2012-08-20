<?php

namespace Foolz\Sphinxql;

class SphinxqlExpression
{
	protected $string;
	
	public function __construct($string = '')
	{
		$this->string = $string;
	}
	
	public function value()
	{
		return (string) $this->string;
	}
	
	public function __to_string()
	{
		return (string) $this->value();
	}
}