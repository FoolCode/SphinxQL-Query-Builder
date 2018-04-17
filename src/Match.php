<?php

namespace Foolz\SphinxQL;

/**
 * Query Builder class for Match statements.
 */
class Match
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
     *    $sub = new Match($sphinxql);
     *    $sub->match('a')->orMatch('b');
     *    $match->match($sub);
     *    // (a | b)
     *
     * @param string|Match|\Closure $keywords The text or expression to match.
     *
     * @return $this
     */
    public function match($keywords = null)
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
     * @param string|Match|\Closure $keywords The text or expression to alternatively match.
     *
     * @return $this
     */
    public function orMatch($keywords = null)
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
     * @param string|Match|\Closure $keywords The text or expression to optionally match.
     *
     * @return $this
     */
    public function maybe($keywords = null)
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
     * @param string $keyword The word not to match.
     *
     * @return $this
     */
    public function not($keyword = null)
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
    public function field($fields, $limit = null)
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
    public function ignoreField($fields)
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
    public function phrase($keywords)
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
    public function orPhrase($keywords)
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
    public function proximity($keywords, $distance)
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
    public function quorum($keywords, $threshold)
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
     * @param string|Match|\Closure $keywords The text or expression that must come after.
     *
     * @return $this
     */
    public function before($keywords = null)
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
    public function exact($keyword = null)
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
    public function boost($keyword, $amount = null)
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
     * @param string|Match|\Closure $keywords The text or expression to match nearby.
     * @param int                   $distance Maximum distance to the match.
     *
     * @return $this
     */
    public function near($keywords, $distance = null)
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
     * @param string|Match|\Closure $keywords The text or expression that must be in the sentence.
     *
     * @return $this
     */
    public function sentence($keywords = null)
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
     * @param string|Match|\Closure $keywords The text or expression that must be in the paragraph.
     *
     * @return $this
     */
    public function paragraph($keywords = null)
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
     * @param string|Match|\Closure $keywords The text or expression that must be in these zones.
     *
     * @return $this
     */
    public function zone($zones, $keywords = null)
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
     * @param string|Match|\Closure $keywords The text or expression that must be in this zone.
     *
     * @return $this
     */
    public function zonespan($zone, $keywords = null)
    {
        $this->tokens[] = array('ZONESPAN' => $zone);
        $this->match($keywords);

        return $this;
    }

    /**
     * Build the match expression.
     *
     * @return $this
     */
    public function compile()
    {
        $query = '';
        foreach ($this->tokens as $token) {
            if (key($token) == 'MATCH') {
                if ($token['MATCH'] instanceof Expression) {
                    $query .= $token['MATCH']->value().' ';
                } elseif ($token['MATCH'] instanceof Match) {
                    $query .= '('.$token['MATCH']->compile()->getCompiled().') ';
                } elseif ($token['MATCH'] instanceof \Closure) {
                    $sub = new static($this->sphinxql);
                    call_user_func($token['MATCH'], $sub);
                    $query .= '('.$sub->compile()->getCompiled().') ';
                } elseif (strpos($token['MATCH'], ' ') === false) {
                    $query .= $this->sphinxql->escapeMatch($token['MATCH']).' ';
                } else {
                    $query .= '('.$this->sphinxql->escapeMatch($token['MATCH']).') ';
                }
            } elseif (key($token) == 'OPERATOR') {
                $query .= $token['OPERATOR'];
            } elseif (key($token) == 'FIELD') {
                $query .= $token['FIELD'];
                if (count($token['fields']) == 1) {
                    $query .= $token['fields'][0];
                } else {
                    $query .= '('.implode(',', $token['fields']).')';
                }
                if ($token['limit']) {
                    $query .= '['.$token['limit'].']';
                }
                $query .= ' ';
            } elseif (key($token) == 'PHRASE') {
                $query .= '"'.$this->sphinxql->escapeMatch($token['PHRASE']).'" ';
            } elseif (key($token) == 'PROXIMITY') {
                $query .= '"'.$this->sphinxql->escapeMatch($token['keywords']).'"~';
                $query .= $token['PROXIMITY'].' ';
            } elseif (key($token) == 'QUORUM') {
                $query .= '"'.$this->sphinxql->escapeMatch($token['keywords']).'"/';
                $query .= $token['QUORUM'].' ';
            } elseif (key($token) == 'BOOST') {
                $query = rtrim($query).'^'.$token['BOOST'].' ';
            } elseif (key($token) == 'NEAR') {
                $query .= 'NEAR/'.$token['NEAR'].' ';
            } elseif (key($token) == 'ZONE') {
                $query .= 'ZONE:('.implode(',', $token['ZONE']).') ';
            } elseif (key($token) == 'ZONESPAN') {
                $query .= 'ZONESPAN:('.$token['ZONESPAN'].') ';
            }
        }
        $this->last_compiled = trim($query);

        return $this;
    }

    /**
     * Returns the latest compiled match expression.
     *
     * @return string The last compiled match expression.
     */
    public function getCompiled()
    {
        return $this->last_compiled;
    }
}
