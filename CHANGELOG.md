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
