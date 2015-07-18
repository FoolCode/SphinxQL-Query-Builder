SphinxQL Query Builder Unit Tests
=================================

##### How to run

There's a sphinx.conf file in this directory. It uses a single RT index. Check the necessary directories, I ran Sphinx in `/usr/local/sphinx`

The udf must be compiled: `gcc -shared -o data/test_udf.so test_udf.c`

The test should then just work: `phpunit -c phpunit.xml`

Make sure there's a `data` directory under the `tests` directory.
