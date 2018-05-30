<?php

use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Percolate;
use Foolz\SphinxQL\Tests\TestUtil;
use Foolz\SphinxQL\SphinxQL;
/**
 * @group Manticore
 * @package Foolz\SphinxQL
 * @author Vicent Valls
 */
class PercolateQueriesTest extends \PHPUnit\Framework\TestCase
{
    public static $conn = null;


    public static function setUpBeforeClass()
    {
	    $conn = TestUtil::getConnectionDriver();
	    $conn->setParam('port', 9307);
	    self::$conn = $conn;

        $sphinxQL = new SphinxQL(self::$conn);
        $sphinxQL->query('TRUNCATE RTINDEX pq')->execute();
    }


    /**
     * @dataProvider insertProvider
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function testInsert($testNumber, $query, $index, $tags, $filter, $compiledQuery)
    {

        if ($testNumber == 2) {
            $this->expectException(SphinxQLException::class);
            $this->expectExceptionMessage('Index can\'t be empty');
        }

        if ($testNumber == 3) {
            $this->expectException(SphinxQLException::class);
            $this->expectExceptionMessage('Query can\'t be empty');
        }

        $percolate = new Percolate(self::$conn);
        $percolate
            ->insert($query)
            ->into($index)
            ->tags($tags)
            ->filter($filter)
            ->execute();

        if (in_array($testNumber, [1, 4, 5, 6, 7, 8, 9, 11])) {
            $this->assertEquals($compiledQuery, $percolate->getLastQuery());
        }


        //$this->markTestIncomplete(true);
    }

    public function insertProvider()
    {

        /**
         * 1) Just insert
         * 2) Insert empty index
         * 3) Insert empty query
         * 4) Insert with special symbols
         * 5) Insert with tags as string without filter
         * 6) Insert with tags as array of string without filter
         * 7) Insert tags with special symbols
         * 8) Insert with filter, withowt tags
         * 9) Insert filter with special symbols
         * 10) Insert two filters
         * 11) Insert filter + tags
         */


        return [
            [
                1,
                'full text query terms',
                'pq',
                null,
                null,
                "INSERT INTO pq (query) VALUES ('full text query terms')"
            ],

            [
                2,
                'full text query terms',
                null,
                null,
                null,
                null
            ],

            [
                3,
                null,
                'pq',
                null,
                null,
                null
            ],

            [
                4,
                '@doc (text) \' ^ $ " | ! ~ / = >< & - \query terms',
                'pq',
                null,
                null,
                'INSERT INTO pq (query) VALUES (\'@doc (text) \\\\\\\' ^ $ \\\\\\" | \\\\! \\\\~ \\\\/ = >\\\\< & \\\\- \\\\\\\\query terms\')'
            ],

            [
                5,
                '@subject match by field',
                'pq',
                'tag2,tag3',
                'price>3',
                "INSERT INTO pq (query, tags, filters) VALUES ('@subject match by field', 'tag2,tag3', 'price>3')"
            ],

            [
                6,
                '@subject orange',
                'pq',
                ['tag2', 'tag3'],
                null,
                "INSERT INTO pq (query, tags) VALUES ('@subject orange', 'tag2,tag3')"
            ],

            [
                7,
                '@subject orange',
                'pq',
                '@doc (text) \' ^ $ " | ! ~ / = >< & - \query terms',
                null,
                'INSERT INTO pq (query, tags) VALUES (\'@subject orange\', \'@doc (text) \\\\\\\' ^ $ \\\\\" | \\\\! \\\\~ \\\\/ = >\\\\< & \\\\- \\\\\\\\query terms\')'
            ],

            [
                8,
                'catch me',
                'pq',
                null,
                'price>3',
                'INSERT INTO pq (query, filters) VALUES (\'catch me\', \'price>3\')'
            ],

            [
                9,
                'catch me if can',
                'pq',
                null,
                'p\@r\'ice>3',
                'INSERT INTO pq (query, filters) VALUES (\'catch me if can\', \'price>3\')'
            ],

            [
                11,
                'orange|apple|cherry',
                'pq',
                ['tag2', 'tag3'],
                'price>3',
                "INSERT INTO pq (query, tags, filters) VALUES ('orange|apple|cherry', 'tag2,tag3', 'price>3')"
            ],
        ];
    }

    /**
     * @dataProvider callPqProvider
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */

    public function testPercolate($testNumber, $index, $documents, $options, $result)
    {
        if ($testNumber == 2) {
            $this->expectException(SphinxQLException::class);
            $this->expectExceptionMessage('Document can\'t be empty');

        } elseif ($testNumber == 3) {
            $this->expectException(SphinxQLException::class);
            $this->expectExceptionMessage('Index can\'t be empty');

        } elseif ($testNumber == 12) {
            $this->expectException(SphinxQLException::class);
            $this->expectExceptionMessage('Documents must be in json format');

        } elseif ($testNumber == 13) {
            $this->expectException(SphinxQLException::class);
            $this->expectExceptionMessage('Documents array must be associate');

        }

        $query = (new Percolate(self::$conn))
            ->callPQ()
            ->from($index)
            ->documents($documents)
            ->options($options)
            ->execute();


        if (in_array($testNumber, [1, 4, 5, 6, 7, 8, 9, 11])) {
            $query = $query->fetchAllAssoc();
            $this->assertEquals($result[0], $query[0]['Query']);
            $this->assertEquals($result[1], count($query));
        }

        if ($testNumber == 10) {
            $query = $query->fetchAllAssoc();
            $this->assertEquals($result[0], $query[0]['UID']);
            $this->assertEquals($result[1], count($query));
        }

    }

    public function callPqProvider()
    {
        /**
         * 1) Call PQ
         * 2) Document empty
         * 3) Index empty
         * 4) Documents array of string
         * 5) Documents associate array
         * 6) Documents array of associate array
         * 7) Documents jsonObject
         * 8) Documents jsonArray of jsonObject
         * 9) Documents phpArray of jsonObject
         * 10) Option OPTION_QUERY
         * 11) Option OPTION_DOCS
         * Throws OPTION_DOCS_JSON
         * 12) Not json string
         * 13) Not array with non json string
         */


        return [
            [1, 'pq', 'full text query terms', [Percolate::OPTION_QUERY => 1], ['full text query terms', 2]],
            [2, 'pq', '', [], null],
            [3, '', 'full', [], null],
            [
                4,
                'pq',
                ['query terms', 'full text query terms'],
                [Percolate::OPTION_QUERY => 1],
                ['full text query terms', 2]
            ],
            [5, 'pq', ['subject' => 'document about orange'], [Percolate::OPTION_QUERY => 1], ['@subject orange', 2]],
            [
                6,
                'pq',
                [['subject' => 'document about orange'], ['subject' => 'match by field', 'price' => 1]],
                [Percolate::OPTION_QUERY => 1],
                ['@subject orange', 2]
            ],
            [7, 'pq', '{"subject":"document about orange"}', [Percolate::OPTION_QUERY => 1], ['@subject orange', 2]],
            [
                8,
                'pq',
                '[{"subject":"document about orange"}, {"subject":"match by field","price":10}]',
                [Percolate::OPTION_QUERY => 1],
                ['@subject match by field', 3]
            ],
            [
                9,
                'pq',
                ['{"subject":"document about orange"}', '{"subject":"match by field","price":10}'],
                [Percolate::OPTION_QUERY => 1],
                ['@subject match by field', 3]
            ],
            [10, 'pq', 'full text query terms', [Percolate::OPTION_QUERY => 0], [1, 2]],
            [
                11,
                'pq',
                ['{"subject":"document about orange"}', '{"subject":"match by field","price":10}'],
                [Percolate::OPTION_QUERY => 1, Percolate::OPTION_DOCS => 1],
                ['@subject match by field', 3]
            ],
            [12, 'pq', 'full text query terms', [Percolate::OPTION_DOCS_JSON => 1], null],
            [13, 'pq', ['full text query terms','full text'], [Percolate::OPTION_DOCS_JSON => 1], null],
        ];
    }

}
