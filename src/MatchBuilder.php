<?php

namespace Foolz\SphinxQL;

use Closure;

/**
 * Query Builder class for MatchBuilder statements.
 */
class MatchBuilder
{
    /**
     * The last compiled query.
     *
     * @var string
     */
    protected $last_compiled;

    /**
     * List of match operations.
     *
     * @var array
     */
    protected $tokens = array();

    /**
     * The owning SphinxQL object; used for escaping text.
     *
     * @var SphinxQL
     */
    protected $sphinxql;

    /**
     * @param SphinxQL $sphinxql
     */
    public function __construct(SphinxQL $sphinxql)
    {
        $this->sphinxql = $sphinxql;
    }

    /**
     * Match text or sub expression.
     *
     * Examples:
     *    $match->match('test');
     *    // test
     *
     *    $match->match('test case');
     *    // (test case)
     *
     *    $match->match(function ($m) {
     *        $m->match('a')->orMatch('b');
     *    });
     *    // (a | b)
     *
     *    $sub = new MatchBuilder($sphinxql);
     *    $sub->match('a')->orMatch('b');
     *    $match->match($sub);
     *    // (a | b)
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression to match.
     *
     * @return $this
     */
    public function match($keywords = null): self
    {
        if ($keywords !== null) {
            $this->tokens[] = array('MATCH' => $keywords);
        }

        return $this;
    }

    /**
     * Provide an alternation match.
     *
     * Examples:
     *    $match->match('test')->orMatch();
     *    // test |
     *
     *    $match->match('test')->orMatch('case');
     *    // test | case
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression to alternatively match.
     *
     * @return $this
     */
    public function orMatch($keywords = null): self
    {
        $this->tokens[] = array('OPERATOR' => '| ');
        $this->match($keywords);

        return $this;
    }

    /**
     * Provide an optional match.
     *
     * Examples:
     *    $match->match('test')->maybe();
     *    // test MAYBE
     *
     *    $match->match('test')->maybe('case');
     *    // test MAYBE case
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression to optionally match.
     *
     * @return $this
     */
    public function maybe($keywords = null): self
    {
        $this->tokens[] = array('OPERATOR' => 'MAYBE ');
        $this->match($keywords);

        return $this;
    }

    /**
     * Do not match a keyword.
     *
     * Examples:
     *    $match->not()->match('test');
     *    // -test
     *
     *    $match->not('test');
     *    // -test
     *
     * @param string|MatchBuilder|\Closure $keyword The word not to match.
     *
     * @return $this
     */
    public function not($keyword = null): self
    {
        $this->tokens[] = array('OPERATOR' => '-');
        $this->match($keyword);

        return $this;
    }

    /**
     * Specify which field(s) to search.
     *
     * Examples:
     *    $match->field('*')->match('test');
     *    // @* test
     *
     *    $match->field('title')->match('test');
     *    // @title test
     *
     *    $match->field('body', 50)->match('test');
     *    // @body[50] test
     *
     *    $match->field('title', 'body')->match('test');
     *    // @(title,body) test
     *
     *    $match->field(['title', 'body'])->match('test');
     *    // @(title,body) test
     *
     *    $match->field('@relaxed')->field('nosuchfield')->match('test');
     *    // @@relaxed @nosuchfield test
     *
     * @param string|array $fields Field or fields to search.
     * @param int          $limit  Maximum position limit in field a match is allowed at.
     *
     * @return $this
     */
    public function field($fields, $limit = null): self
    {
        if (is_string($fields)) {
            $fields = func_get_args();
            $limit = null;
        }
        if (!is_string(end($fields))) {
            $limit = array_pop($fields);
        }
        $this->tokens[] = array(
            'FIELD'  => '@',
            'fields' => $fields,
            'limit'  => $limit,
        );

        return $this;
    }

    /**
     * Specify which field(s) not to search.
     *
     * Examples:
     *    $match->ignoreField('title')->match('test');
     *    // @!title test
     *
     *    $match->ignoreField('title', 'body')->match('test');
     *    // @!(title,body) test
     *
     *    $match->ignoreField(['title', 'body'])->match('test');
     *    // @!(title,body) test
     *
     * @param string|array $fields Field or fields to ignore during search.
     *
     * @return $this
     */
    public function ignoreField($fields): self
    {
        if (is_string($fields)) {
            $fields = func_get_args();
        }
        $this->tokens[] = array(
            'FIELD'  => '@!',
            'fields' => $fields,
            'limit'  => null,
        );

        return $this;
    }

    /**
     * Match an exact phrase.
     *
     * Example:
     *    $match->phrase('test case');
     *    // "test case"
     *
     * @param string $keywords The phrase to match.
     *
     * @return $this
     */
    public function phrase($keywords): self
    {
        $this->tokens[] = array('PHRASE' => $keywords);

        return $this;
    }

    /**
     * Provide an optional phrase.
     *
     * Example:
     *    $match->phrase('test case')->orPhrase('another case');
     *    // "test case" | "another case"
     *
     * @param string $keywords The phrase to match.
     *
     * @return $this
     */
    public function orPhrase($keywords): self
    {
        $this->tokens[] = array('OPERATOR' => '| ');
        $this->phrase($keywords);

        return $this;
    }

    /**
     * Match if keywords are close enough.
     *
     * Example:
     *    $match->proximity('test case', 5);
     *    // "test case"~5
     *
     * @param string $keywords The words to match.
     * @param int    $distance The upper limit on separation between words.
     *
     * @return $this
     */
    public function proximity($keywords, $distance): self
    {
        $this->tokens[] = array(
            'PROXIMITY' => $distance,
            'keywords'  => $keywords,
        );

        return $this;
    }

    /**
     * Match if enough keywords are present.
     *
     * Examples:
     *    $match->quorum('this is a test case', 3);
     *    // "this is a test case"/3
     *
     *    $match->quorum('this is a test case', 0.5);
     *    // "this is a test case"/0.5
     *
     * @param string    $keywords  The words to match.
     * @param int|float $threshold The minimum number or percent of words that must match.
     *
     * @return $this
     */
    public function quorum($keywords, $threshold): self
    {
        $this->tokens[] = array(
            'QUORUM'   => $threshold,
            'keywords' => $keywords,
        );

        return $this;
    }

    /**
     * Assert keywords or expressions must be matched in order.
     *
     * Examples:
     *    $match->match('test')->before();
     *    // test <<
     *
     *    $match->match('test')->before('case');
     *    // test << case
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression that must come after.
     *
     * @return $this
     */
    public function before($keywords = null): self
    {
        $this->tokens[] = array('OPERATOR' => '<< ');
        $this->match($keywords);

        return $this;
    }

    /**
     * Assert a keyword must be matched exactly as written.
     *
     * Examples:
     *    $match->match('test')->exact('cases');
     *    // test =cases
     *
     *    $match->match('test')->exact()->phrase('specific cases');
     *    // test ="specific cases"
     *
     * @param string $keyword The word that must be matched exactly.
     *
     * @return $this
     */
    public function exact($keyword = null): self
    {
        $this->tokens[] = array('OPERATOR' => '=');
        $this->match($keyword);

        return $this;
    }

    /**
     * Boost the IDF score of a keyword.
     *
     * Examples:
     *    $match->match('test')->boost(1.2);
     *    // test^1.2
     *
     *    $match->match('test')->boost('case', 1.2);
     *    // test case^1.2
     *
     * @param string $keyword The word to modify the score of.
     * @param float  $amount  The amount to boost the score.
     *
     * @return $this
     */
    public function boost($keyword, $amount = null): self
    {
        if ($amount === null) {
            $amount = $keyword;
        } else {
            $this->match($keyword);
        }
        $this->tokens[] = array('BOOST' => $amount);

        return $this;
    }

    /**
     * Assert keywords or expressions must be matched close to each other.
     *
     * Examples:
     *    $match->match('test')->near(3);
     *    // test NEAR/3
     *
     *    $match->match('test')->near('case', 3);
     *    // test NEAR/3 case
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression to match nearby.
     * @param int                   $distance Maximum distance to the match.
     *
     * @return $this
     */
    public function near($keywords, $distance = null): self
    {
        $this->tokens[] = array('NEAR' => $distance ?: $keywords);
        if ($distance !== null) {
            $this->match($keywords);
        }

        return $this;
    }

    /**
     * Assert matches must be in the same sentence.
     *
     * Examples:
     *    $match->match('test')->sentence();
     *    // test SENTENCE
     *
     *    $match->match('test')->sentence('case');
     *    // test SENTENCE case
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression that must be in the sentence.
     *
     * @return $this
     */
    public function sentence($keywords = null): self
    {
        $this->tokens[] = array('OPERATOR' => 'SENTENCE ');
        $this->match($keywords);

        return $this;
    }

    /**
     * Assert matches must be in the same paragraph.
     *
     * Examples:
     *    $match->match('test')->paragraph();
     *    // test PARAGRAPH
     *
     *    $match->match('test')->paragraph('case');
     *    // test PARAGRAPH case
     *
     * @param string|MatchBuilder|\Closure $keywords The text or expression that must be in the paragraph.
     *
     * @return $this
     */
    public function paragraph($keywords = null): self
    {
        $this->tokens[] = array('OPERATOR' => 'PARAGRAPH ');
        $this->match($keywords);

        return $this;
    }

    /**
     * Assert matches must be in the specified zone(s).
     *
     * Examples:
     *    $match->zone('th');
     *    // ZONE:(th)
     *
     *    $match->zone(['h3', 'h4']);
     *    // ZONE:(h3,h4)
     *
     *    $match->zone('th', 'test');
     *    // ZONE:(th) test
     *
     * @param string|array          $zones    The zone or zones to search.
     * @param string|MatchBuilder|\Closure $keywords The text or expression that must be in these zones.
     *
     * @return $this
     */
    public function zone($zones, $keywords = null): self
    {
        if (is_string($zones)) {
            $zones = array($zones);
        }
        $this->tokens[] = array('ZONE' => $zones);
        $this->match($keywords);

        return $this;
    }


    /**
     * Assert matches must be in the same instance of the specified zone.
     *
     * Examples:
     *    $match->zonespan('th');
     *    // ZONESPAN:(th)
     *
     *    $match->zonespan('th', 'test');
     *    // ZONESPAN:(th) test
     *
     * @param string                $zone     The zone to search.
     * @param string|MatchBuilder|\Closure $keywords The text or expression that must be in this zone.
     *
     * @return $this
     */
    public function zonespan($zone, $keywords = null): self
    {
        $this->tokens[] = array('ZONESPAN' => $zone);
        $this->match($keywords);

        return $this;
    }

    /**
     * Build the match expression.
     * @return $this
     */
    public function compile(): self
    {
        $query = '';

        foreach ($this->tokens as $token) {
            $tokenKey = key($token);

            switch ($tokenKey) {
                case 'MATCH':{
                	$query .= $this->compileMatch($token['MATCH']);
                    break;
                }
                case 'OPERATOR':{
                    $query .= $token['OPERATOR'];
                    break;
                }
                case 'FIELD':{
                	$query .= $this->compileField($token['FIELD'],$token['fields'],$token['limit']);
                    break;
                }
                case 'PHRASE':{
                    $query .= '"'.$this->sphinxql->escapeMatch($token['PHRASE']).'" ';
                    break;
                }
                case 'PROXIMITY':{
                    $query .= '"'.$this->sphinxql->escapeMatch($token['keywords']).'"~';
                    $query .= $token['PROXIMITY'].' ';
                    break;
                }
                case 'QUORUM':{
                    $query .= '"'.$this->sphinxql->escapeMatch($token['keywords']).'"/';
                    $query .= $token['QUORUM'].' ';
                    break;
                }
                case 'BOOST':{
                    $query = rtrim($query).'^'.$token['BOOST'].' ';
                    break;
                }
                case 'NEAR':{
                    $query .= 'NEAR/'.$token['NEAR'].' ';
                    break;
                }
                case 'ZONE':{
                    $query .= 'ZONE:('.implode(',', $token['ZONE']).') ';
                    break;
                }
                case 'ZONESPAN':{
                    $query .= 'ZONESPAN:('.$token['ZONESPAN'].') ';
                    break;
                }
            }
        }

        $this->last_compiled = trim($query);

        return $this;
    }

    private function compileMatch($token): string{
		if ($token instanceof Expression) {
			return $token->value().' ';
		}
		if ($token instanceof self) {
			return '('.$token->compile()->getCompiled().') ';
		}
		if ($token instanceof Closure) {
			$sub = new static($this->sphinxql);
			$token($sub);
			return '('.$sub->compile()->getCompiled().') ';
		}
		if (strpos($token, ' ') === false) {
			return $this->sphinxql->escapeMatch($token).' ';
		}
		return '('.$this->sphinxql->escapeMatch($token).') ';
	}

	private function compileField($token,$fields,$limit): string{
    	$query = $token;

		if (count($fields) === 1) {
			$query .= $fields[0];
		} else {
			$query .= '('.implode(',', $fields).')';
		}
		if ($limit) {
			$query .= '['.$limit.']';
		}
		$query .= ' ';

		return $query;
	}

    /**
     * Returns the latest compiled match expression.
     *
     * @return string The last compiled match expression.
     */
    public function getCompiled(): string
    {
        return $this->last_compiled;
    }
}
