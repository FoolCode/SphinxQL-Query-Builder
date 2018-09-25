#### 2.0.0

* added support for [Manticore](https://manticoresearch.com)
* added `Percolate` class for `Manticore`
* added `orPhrase` method to `Match` class
* added `resetFacets` method to `SphinxQL` class
* added support for multi-document snippet call
* fixed `Connection` exception thrown
* fixed incorrect property accessibility/visibility
* refactored `ResultSet` and `MultiResultSet` classes to reduce duplicate code
* removed `Connection` error suppression
* removed `SphinxQL\Drivers\ResultSetAdapterInterface` constants
* removed static `SphinxQL::create` method
* removed deprecated `\Foolz\SphinxQL\Connection`
* removed support for PHP 5.3 and HHVM
* updated fetch type for drivers to use `boolean` to return assoc/indexed arrays
* updated PHPDoc blocks

Note: This release contains **breaking changes** around the instantiation of the `SphinxQL` class with the removal of static methods. Please refer to the README for any API changes.

#### 1.2.0

* added support for `GROUP N BY`
* refactored `Connection`, `\Foolz\SphinxQL\Connection` is now deprecated.
* refactored `ResultSet` and `MultiResultSet` to reduce duplicate code

Note: This release contains **breaking changes** with the introduction of `ResultSet` and `MultiResultSet` changes. Please refer to the README for any API changes.

#### 0.9.7

* added support for unix sockets
* added `NOT IN` condition in `WHERE` statements

#### 0.9.6

* added named integer lists support to `OPTION` with associative array (@alpha0010)
* deprecated special case `OPTION` for `field_weights` and `index_weights`
* forced `Connection` to use utf8 charset (@t1gor)

#### 0.9.5
* `Expression` support for `OPTION` value

#### 0.9.4
* Replaced `getConnectionParams()` and `setConnectionParams()` with `getParam()`, `getParams()`, `setParam()` (thanks to @FindTheBest)
* Deprecated `getConnectionParams()` and `setConnectionParams()`
* Added `ConnectionInterface`

#### 0.9.3

* HHVM support
* Added escaping of new MATCH features by lowercasing the search string

#### 0.9.2

* created `Helper` class to contain non-query-builder query methods, all returning `SphinxQL` objects
* deprecated all non-query-builder query methods in `SphinxQL` class
* improved `$sq->enqueue()` in `SphinxQL` class to have a parameter to append any custom `SphinxQL` objects
* added `$sq->query()` method to `SphinxQL` to allow setting SQL queries without executing them

#### 0.9.1

* deprecated SphinxQL::forge() with static Connection and implemented SphinxQL::create($conn)
* added array and * support to MATCH columns (thanks to @FindTheBest)
* added Expression support to MATCH value

#### 0.9.0

* refactored to be fully OOP
* changed code style to be PSR-2 compliant
* removed all unnecessary `static` keywords
* removed old bootstrap file for fuelphp

#### 0.8.6

* added Connection::ping()
* added Connection::close()
* fixed uncaught exception thrown by Connection::getConnection()

#### 0.8.5

* removed Array typehints
* removed unsupported charset argument

#### 0.8.4

* fixed composer bootstrap
* removed `Sphinxql` prefix on Connection and Expression classes

#### 0.8.3

* added Queue support

#### 0.8.2

* fixed composer bootstrap

#### 0.8.1

* improved phpunit tests

#### 0.8.0

* initial release
