<?php

namespace Foolz\SphinxQL;

use Foolz\SphinxQL\Drivers\ConnectionInterface;
use Foolz\SphinxQL\Exception\SphinxQLException;

/**
 * Query Builder class for Percolate Queries.
 *
 * ### INSERT ###
 *
 * $query = (new Percolate($conn))
 *    ->insert('full text query terms', noEscape = false)       // Allowed only one insert per query (Symbol @ indicates field in sphinx.conf)
 *                                                                 No escape tag cancels characters shielding (default on)
 *    ->into('pq')                                              // Index for insert
 *    ->tags(['tag1','tag2'])                                   // Adding tags. Can be array ['tag1','tag2'] or string delimited by coma
 *    ->filter('price>3')                                       // Adding filter (Allowed only one)
 *    ->execute();
 *
 *
 * ### CALL PQ ###
 *
 *
 * $query = (new Percolate($conn))
 *    ->callPQ()
 *    ->from('pq')                                              // Index for call pq
 *    ->documents(['multiple documents', 'go this way'])        // see getDocuments function
 *    ->options([                                               // See https://docs.manticoresearch.com/latest/html/searching/percolate_query.html#call-pq
 *          Percolate::OPTION_VERBOSE => 1,
 *          Percolate::OPTION_DOCS_JSON => 1
 *    ])
 *    ->execute();
 *
 *
 */
class Percolate
{

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * Documents for CALL PQ
     *
     * @var array|string
     */
    protected $documents;

    /**
     * Index name
     *
     * @var string
     */
    protected $index;

    /**
     * Insert query
     *
     * @var string
     */
    protected $query;

    /**
     * Options for CALL PQ
     * @var array
     */
    protected $options = [self::OPTION_DOCS_JSON => 1];

    /**
     * @var string
     */
    protected $filters = '';

    /**
     * Query type (call | insert)
     *
     * @var string
     */
    protected $type = 'call';

    /** INSERT STATEMENT  **/

    protected $tags = [];

    /**
     * Throw exceptions flag.
     * Activates if option OPTION_DOCS_JSON setted
     *
     * @var int
     */
    protected $throwExceptions = 0;
    /**
     * @var array
     */
    protected $escapeChars = [
        '\\' => '\\\\',
        '-' => '\\-',
        '~' => '\\~',
        '<' => '\\<',
        '"' => '\\"',
        "'" => "\\'",
        '/' => '\\/',
        '!' => '\\!'
    ];

    /** @var SphinxQL */
    protected $sphinxQL;

    /**
     * CALL PQ option constants
     */
    const OPTION_DOCS_JSON = 'as docs_json';
    const OPTION_DOCS = 'as docs';
    const OPTION_VERBOSE = 'as verbose';
    const OPTION_QUERY = 'as query';

    /**
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->sphinxQL = new SphinxQL($this->connection);

    }


    /**
     * Clear all fields after execute
     */
    private function clear()
    {
        $this->documents = null;
        $this->index = null;
        $this->query = null;
        $this->options = [self::OPTION_DOCS_JSON => 1];
        $this->type = 'call';
        $this->filters = '';
        $this->tags = [];
    }

    /**
     * Analog into function
     * Sets index name for query
     *
     * @param string $index
     *
     * @return $this
     * @throws SphinxQLException
     */
    public function from($index)
    {
        if (empty($index)) {
            throw new SphinxQLException('Index can\'t be empty');
        }

        $this->index = trim($index);
        return $this;
    }

    /**
     * Analog from function
     * Sets index name for query
     *
     * @param string $index
     *
     * @return $this
     * @throws SphinxQLException
     */
    public function into($index)
    {
        if (empty($index)) {
            throw new SphinxQLException('Index can\'t be empty');
        }
        $this->index = trim($index);
        return $this;
    }

    /**
     * Replacing bad chars
     *
     * @param string $query
     *
     * @return string mixed
     */
    protected function escapeString($query)
    {
        return str_replace(
            array_keys($this->escapeChars),
            array_values($this->escapeChars),
            $query);
    }


    /**
     * @param $query
     * @return mixed
     */
    protected function clearString($query)
    {
        return str_replace(
            array_keys(array_merge($this->escapeChars, ['@' => ''])),
            ['', '', '', '', '', '', '', '', '', ''],
            $query);
    }

    /**
     * Adding tags for insert query
     *
     * @param array|string $tags
     *
     * @return $this
     */
    public function tags($tags)
    {
        if (is_array($tags)) {
            $tags = array_map([$this, 'escapeString'], $tags);
            $tags = implode(',', $tags);
        } else {
            $tags = $this->escapeString($tags);
        }
        $this->tags = $tags;
        return $this;
    }

    /**
     * Add filter for insert query
     *
     * @param string $filter
     * @return $this
     *
     * @throws SphinxQLException
     */
    public function filter($filter)
    {
        $this->filters = $this->clearString($filter);
        return $this;
    }

    /**
     * Add insert query
     *
     * @param string $query
     * @param bool $noEscape
     *
     * @return $this
     * @throws SphinxQLException
     */
    public function insert($query, $noEscape = false)
    {
        $this->clear();

        if (empty($query)) {
            throw new SphinxQLException('Query can\'t be empty');
        }
        if (!$noEscape) {
            $query = $this->escapeString($query);
        }
        $this->query = $query;
        $this->type = 'insert';

        return $this;
    }

    /**
     * Generate array for insert, from setted class parameters
     *
     * @return array
     */
    private function generateInsert()
    {
        $insertArray = ['query' => $this->query];

        if (!empty($this->tags)) {
            $insertArray['tags'] = $this->tags;
        }

        if (!empty($this->filters)) {
            $insertArray['filters'] = $this->filters;
        }

        return $insertArray;
    }

    /**
     * Executs query and clear class parameters
     *
     * @return Drivers\ResultSetInterface
     * @throws Exception\ConnectionException
     * @throws Exception\DatabaseException
     * @throws SphinxQLException
     */
    public function execute()
    {

        if ($this->type == 'insert') {
            return $this->sphinxQL
                ->insert()
                ->into($this->index)
                ->set($this->generateInsert())
                ->execute();
        }

        return $this->sphinxQL
            ->query("CALL PQ ('" .
                $this->index . "', " . $this->getDocuments() . " " . $this->getOptions() . ")")
            ->execute();
    }

    /**
     * Set one option for CALL PQ
     *
     * @param string $key
     * @param int $value
     *
     * @return $this
     * @throws SphinxQLException
     */
    private function setOption($key, $value)
    {
        $value = intval($value);
        if (!in_array($key, [
            self::OPTION_DOCS_JSON,
            self::OPTION_DOCS,
            self::OPTION_VERBOSE,
            self::OPTION_QUERY
        ])) {
            throw new SphinxQLException('Unknown option');
        }

        if ($value != 0 && $value != 1) {
            throw new SphinxQLException('Option value can be only 1 or 0');
        }

        if ($key == self::OPTION_DOCS_JSON) {
            $this->throwExceptions = 1;
        }

        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Set document parameter for CALL PQ
     *
     * @param array|string $documents
     * @return $this
     * @throws SphinxQLException
     */
    public function documents($documents)
    {
        if (empty($documents)) {
            throw new SphinxQLException('Document can\'t be empty');
        }
        $this->documents = $documents;

        return $this;
    }

    /**
     * Set options for CALL PQ
     *
     * @param array $options
     * @return $this
     * @throws SphinxQLException
     */
    public function options(array $options)
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
        return $this;
    }


    /**
     * Get and prepare options for CALL PQ
     *
     * @return string string
     */
    protected function getOptions()
    {
        $options = '';
        if (!empty($this->options)) {
            foreach ($this->options as $option => $value) {
                $options .= ', ' . $value . ' ' . $option;
            }
        }

        return $options;
    }

    /**
     * Check is array associative
     * @param array $arr
     * @return bool
     */
    private function isAssocArray(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Get documents for CALL PQ. If option setted JSON - returns json_encoded
     *
     * Now selection of supported types work automatically. You don't need set
     * OPTION_DOCS_JSON to 1 or 0. But if you will set this option,
     * automatically enables exceptions on unsupported types
     *
     *
     * 1) If expect json = 0:
     *      a) doc can be 'catch me'
     *      b) doc can be multiple ['catch me if can','catch me']
     *
     * 2) If expect json = 1:
     *      a) doc can be jsonOBJECT {"subject": "document about orange"}
     *      b) docs can be jsonARRAY of jsonOBJECTS [{"subject": "document about orange"}, {"doc": "document about orange"}]
     *      c) docs can be phpArray of jsonObjects ['{"subject": "document about orange"}', '{"doc": "document about orange"}']
     *      d) doc can be associate array ['subject'=>'document about orange']
     *      e) docs can be array of associate arrays [['subject'=>'document about orange'], ['doc'=>'document about orange']]
     *
     *
     *
     *
     * @return string
     * @throws SphinxQLException
     */
    protected function getDocuments()
    {
        if (!empty($this->documents)) {

            if ($this->throwExceptions) {

                if ($this->options[self::OPTION_DOCS_JSON]) {

                    if (!is_array($this->documents)) {
                        $json = $this->prepareFromJson($this->documents);
                        if (!$json) {
                            throw new SphinxQLException('Documents must be in json format');
                        }
                    } else {
                        if (!$this->isAssocArray($this->documents) && !is_array($this->documents[0])) {
                            throw new SphinxQLException('Documents array must be associate');
                        }
                    }
                }
            }

            if (is_array($this->documents)) {

                // If input is phpArray with json like
                // ->documents(['{"body": "body of doc 1", "title": "title of doc 1"}',
                //             '{"subject": "subject of doc 3"}'])
                //
                // Checking first symbol of first array value. If [ or { - call checkJson

                if (!empty($this->documents[0]) && !is_array($this->documents[0]) &&
                    ($this->documents[0][0] == '[' || $this->documents[0][0] == '{')) {

                    $json = $this->prepareFromJson($this->documents);
                    if ($json) {
                        $this->options[self::OPTION_DOCS_JSON] = 1;
                        return $json;
                    }

                } else {
                    if (!$this->isAssocArray($this->documents)) {

                        // if incoming single array like ['catch me if can', 'catch me']

                        if (is_string($this->documents[0])) {
                            $this->options[self::OPTION_DOCS_JSON] = 0;
                            return '(' . $this->quoteString(implode('\', \'', $this->documents)) . ')';
                        }

                        // if doc is array of associate arrays [['foo'=>'bar'], ['foo1'=>'bar1']]
                        if (!empty($this->documents[0]) && $this->isAssocArray($this->documents[0])) {
                            $this->options[self::OPTION_DOCS_JSON] = 1;
                            return $this->convertArrayForQuery($this->documents);
                        }

                    } else {
                        if ($this->isAssocArray($this->documents)) {
                            // Id doc is associate array ['foo'=>'bar']
                            $this->options[self::OPTION_DOCS_JSON] = 1;
                            return $this->quoteString(json_encode($this->documents));
                        }
                    }
                }

            } else {
                if (is_string($this->documents)) {

                    $json = $this->prepareFromJson($this->documents);
                    if ($json) {
                        $this->options[self::OPTION_DOCS_JSON] = 1;
                        return $json;
                    }

                    $this->options[self::OPTION_DOCS_JSON] = 0;
                    return $this->quoteString($this->documents);
                }
            }
        }
        throw new SphinxQLException('Documents can\'t be empty');
    }


    /**
     * Set type
     *
     * @return $this
     */
    public function callPQ()
    {
        $this->clear();
        $this->type = 'call';
        return $this;
    }


    /**
     * Prepares documents for insert in valid format.
     * $data can be jsonArray of jsonObjects,
     * phpArray of jsonObjects, valid json or string
     *
     * @param string|array $data
     *
     * @return bool|string
     */
    private function prepareFromJson($data)
    {
        if (is_array($data)) {
            if (is_array($data[0])) {
                return false;
            }
            $return = [];
            foreach ($data as $item) {
                $return[] = $this->prepareFromJson($item);
            }

            return '(' . implode(', ', $return) . ')';
        }
        $array = json_decode($data, true);

        if (json_last_error() == JSON_ERROR_NONE) { // if json
            if ( ! empty($array[0])) { // If docs is jsonARRAY of jsonOBJECTS
                return $this->convertArrayForQuery($array);
            }

            // If docs is jsonOBJECT
            return $this->quoteString($data);
        }

        return false;
    }


    /**
     * Converts array of associate arrays to valid for query statement
     * ('jsonOBJECT1', 'jsonOBJECT2' ...)
     *
     *
     * @param array $array
     * @return string
     */
    private function convertArrayForQuery(array $array)
    {
        $documents = [];
        foreach ($array as $document) {
            $documents[] = json_encode($document);
        }

        return '(' . $this->quoteString(implode('\', \'', $documents)) . ')';
    }


    /**
     * Adding single quotes to string
     *
     * @param string $string
     * @return string
     */
    private function quoteString($string)
    {
        return '\'' . $string . '\'';
    }


    /**
     * Get last compiled query
     *
     * @return string
     */
    public function getLastQuery()
    {
        return $this->sphinxQL->getCompiled();
    }
}
