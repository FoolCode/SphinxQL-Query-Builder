#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    WORK=$HOME/search
    gcc -shared -o data/test_udf.so test_udf.c
    $WORK/usr/bin/searchd -c sphinx.conf
    ;;
  SPHINX3)
    WORK=$HOME/search/sphinx-3.0.3
    gcc -shared -o data/test_udf.so $HOME/src/udfexample.c
    $HOME/search/sphinx-3.0.3/bin/searchd -c sphinx.conf
    ;;
  MANTICORE)
    WORK=$HOME/search
    gcc -shared -o data/test_udf.so ms_test_udf.c
    $WORK/usr/bin/searchd -c manticore.conf
    ;;
esac
