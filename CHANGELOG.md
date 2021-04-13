#### 3.0.0
* Added support for PHP 8
* Dropped support for PHP 7.0 and lower
* Renamed `Foolz\SphinxQL\Match` to `Foolz\SphinxQL\MatchBuilder` (BREAKING CHANGE)

#### 2.1.0
* Added exception code and previous throwable to errors
* Added `setType` method to `SphinxQL` class
* Added support for `MATCH` to `DELETE` queries
* Updated MySQLi driver to silence internal warnings by default

#### 2.0.0
* Added support for [Manticore](https://manticoresearch.com)
* Added `Percolate` class for `Manticore`
* Added `orPhrase` method to `Match` class
* Added `resetFacets` method to `SphinxQL` class
* Added support for multi-document snippet call
* Fixed `Connection` exception thrown
* Fixed incorrect property accessibility/visibility
* Refactored `ResultSet` and `MultiResultSet` classes to reduce duplicate code
* Removed `Connection` error suppression
* Removed `SphinxQL\Drivers\ResultSetAdapterInterface` constants
* Removed static `SphinxQL::create` method
* Removed deprecated `\Foolz\SphinxQL\Connection`
* Removed support for PHP 5.3 and HHVM
* Updated fetch type for drivers to use `boolean` to return assoc/indexed arrays
* Updated PHPDoc blocks

Note: This release contains **breaking changes** around the instantiation of the `SphinxQL` class with the removal of static methods. Please refer to the README for any API changes.

#### 1.2.0
* Added support for `GROUP N BY`
* Refactored `Connection`, `\Foolz\SphinxQL\Connection` is now deprecated.
* Refactored `ResultSet` and `MultiResultSet` to reduce duplicate code

Note: This release contains **breaking changes** with the introduction of `ResultSet` and `MultiResultSet` changes. Please refer to the README for any API changes.

#### 0.9.7
* Added support for unix sockets
* Added `NOT IN` condition in `WHERE` statements

#### 0.9.6
* Added named integer lists support to `OPTION` with associative array (@alpha0010)
* Deprecated special case `OPTION` for `field_weights` and `index_weights`
* Forced `Connection` to use utf8 charset (@t1gor)

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
* Created `Helper` class to contain non-query-builder query methods, all returning `SphinxQL` objects
* Deprecated all non-query-builder query methods in `SphinxQL` class
* Improved `$sq->enqueue()` in `SphinxQL` class to have a parameter to append any custom `SphinxQL` objects
* Added `$sq->query()` method to `SphinxQL` to allow setting SQL queries without executing them

#### 0.9.1
* Deprecated SphinxQL::forge() with static Connection and implemented SphinxQL::create($conn)
* Added array and * support to MATCH columns (thanks to @FindTheBest)
* Added Expression support to MATCH value

#### 0.9.0
* Refactored to be fully OOP
* Changed code style to be PSR-2 compliant
* Removed all unnecessary `static` keywords
* Removed old bootstrap file for fuelphp

#### 0.8.6
* Added Connection::ping()
* Added Connection::close()
* Fixed uncaught exception thrown by Connection::getConnection()

#### 0.8.5
* Removed Array typehints
* Removed unsupported charset argument

#### 0.8.4
* Fixed composer bootstrap
* Removed `Sphinxql` prefix on Connection and Expression classes

#### 0.8.3
* Added Queue support

#### 0.8.2
* Fixed composer bootstrap

#### 0.8.1
* Improved phpunit tests

#### 0.8.0
* Initial release
