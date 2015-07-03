<?php

namespace Foolz\SphinxQL;

class Match
{
    protected $query = null;
    
    protected $tokens = array();
    
    protected $sphinxql = null;

    public function __construct(SphinxQL $sphinxql)
    {
        $this->sphinxql = $sphinxql;
    }

    public function match($keywords)
    {
        $this->tokens[] = array('MATCH' => $keywords);
        return $this;
    }

    public function orMatch($keywords = null)
    {
        $this->tokens[] = array('OP' => '| ');
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function maybe($keywords = null)
    {
        $this->tokens[] = array('OP' => 'MAYBE ');
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function not($keyword = null)
    {
        $this->tokens[] = array('OP' => '-');
        if ($keyword !== null) {
            $this->match($keyword);
        }
        return $this;
    }

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

    public function ignoreField($fields, $limit = null)
    {
        if (is_string($fields)) {
            $fields = func_get_args();
            $limit = null;
        }
        if (!is_string(end($fields))) {
            $limit = array_pop($fields);
        }
        $this->tokens[] = array(
            'FIELD'  => '@!',
            'fields' => $fields,
            'limit'  => $limit,
        );
        return $this;
    }

    public function phrase($keywords)
    {
        $this->tokens[] = array('PHRASE' => $keywords);
        return $this;
    }

    public function proximity($keywords, $distance)
    {
        $this->tokens[] = array(
            'PROXIMITY' => $distance,
            'keywords'  => $keywords,
        );
        return $this;
    }

    public function quorum($keywords, $threshold)
    {
        $this->tokens[] = array(
            'QUORUM'   => $threshold,
            'keywords' => $keywords,
        );
        return $this;
    }

    public function before($keywords = null)
    {
        $this->tokens[] = array('OP' => '<< ');
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function exact($keyword = null)
    {
        $this->tokens[] = array('OP' => '=');
        if ($keyword !== null) {
            $this->match($keyword);
        }
        return $this;
    }

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

    public function near($keywords, $distance = null)
    {
        $this->tokens[] = array('NEAR' => $distance ?: $keywords);
        if ($distance !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function sentence($keywords = null)
    {
        $this->tokens[] = array('OP' => 'SENTENCE ');
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function paragraph($keywords = null)
    {
        $this->tokens[] = array('OP' => 'PARAGRAPH ');
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function zone($zones, $keywords = null)
    {
        if (is_string($zones)) {
            $zones = array($zones);
        }
        $this->tokens[] = array('ZONE' => $zones);
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function zonespan($zone, $keywords = null)
    {
        $this->tokens[] = array('ZONESPAN' => $zone);
        if ($keywords !== null) {
            $this->match($keywords);
        }
        return $this;
    }

    public function compile()
    {
        $this->query = '';
        foreach ($this->tokens as $token) {
            if (key($token) == 'MATCH') {
                if ($token['MATCH'] instanceof Expression) {
                    $this->query .= $token['MATCH']->value().' ';
                } elseif ($token['MATCH'] instanceof Match) {
                    $this->query .= '('.$token['MATCH']->compile()->getCompiled().') ';
                } elseif (is_callable($token['MATCH'])) {
                    $sub = new static($this->sphinxql);
                    call_user_func($token['MATCH'], $sub);
                    $this->query .= '('.$sub->compile()->getCompiled().') ';
                } elseif (strpos($token['MATCH'], ' ') === false) {
                    $this->query .= $this->sphinxql->escapeMatch($token['MATCH']).' ';
                } else {
                    $this->query .= '('.$this->sphinxql->escapeMatch($token['MATCH']).') ';
                }
            } elseif (key($token) == 'OP') {
                $this->query .= $token['OP'];
            } elseif (key($token) == 'FIELD') {
                $this->query .= $token['FIELD'];
                if (count($token['fields']) == 1) {
                    $this->query .= $token['fields'][0];
                } else {
                    $this->query .= '('.implode(',', $token['fields']).')';
                }
                if ($token['limit']) {
                    $this->query .= '['.$token['limit'].']';
                }
                $this->query .= ' ';
            } elseif (key($token) == 'PHRASE') {
                $this->query .= '"'.$this->sphinxql->escapeMatch($token['PHRASE']).'" ';
            } elseif (key($token) == 'PROXIMITY') {
                $this->query .= '"'.$this->sphinxql->escapeMatch($token['keywords']).'"~';
                $this->query .= $token['PROXIMITY'].' ';
            } elseif (key($token) == 'QUORUM') {
                $this->query .= '"'.$this->sphinxql->escapeMatch($token['keywords']).'"/';
                $this->query .= $token['QUORUM'].' ';
            } elseif (key($token) == 'BOOST') {
                $this->query = rtrim($this->query).'^'.$token['BOOST'].' ';
            } elseif (key($token) == 'NEAR') {
                $this->query .= 'NEAR/'.$token['NEAR'].' ';
            } elseif (key($token) == 'ZONE') {
                $this->query .= 'ZONE:('.implode(',', $token['ZONE']).') ';
            } elseif (key($token) == 'ZONESPAN') {
                $this->query .= 'ZONESPAN:('.$token['ZONESPAN'].') ';
            }
        }
        $this->query = trim($this->query);
        return $this;
    }

    public function getCompiled()
    {
        return $this->query;
    }

}
