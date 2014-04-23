SphinxQL Query Builder Unit Tests
=================================

##### How to run

There's a sphinx.conf file in this directory. It uses a single RT index. Check the necessary directories, I ran Sphinx in `/usr/local/sphinx`

The test should then just work: `phpunit -c phpunit.xml`

Make sure there's a `data` directory under the `tests` directory.

##### Notes

There are a few functions not comprehended in the unit testing.

* `callSnippets`
* `callKeywords`
* `createFunction`
* `dropFunction`
* `attachIndex`
* `flushRtIndex`

They may be added later in time.
