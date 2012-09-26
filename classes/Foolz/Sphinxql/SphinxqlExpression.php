<?php
namespace Foolz\Sphinxql;

/**
 * Wraps expressions so they aren't quoted or modified
 * when inserted into the query
 */
class SphinxqlExpression
{
	/**
	 * The expression
	 * 
	 * @var string 
	 */
	protected $string;
	
	
	/**
	 * The constructor accepts the expression as string
	 * 
	 * @param type $string
	 */
	public function __construct($string = '')
	{
		$this->string = $string;
	}
	
	
	/**
	 * Return the unmodified expression
	 * 
	 * @return type
	 */
	public function value()
	{
		return (string) $this->string;
	}
	
	
	/**
	 * returns the unmodified expression
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->value();
	}
}