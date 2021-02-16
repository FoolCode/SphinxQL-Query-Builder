#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    gcc -shared -o ../data/test_udf.so test_udf.c
    $WORK/usr/bin/searchd -c sphinx.conf
    ;;
  SPHINX3)
    gcc -shared -o ../data/test_udf.so $WORK/src/udfexample.c
    $WORK/bin/searchd -c sphinx.conf
    ;;
  MANTICORE)
    gcc -shared -o ../data/test_udf.so ms_test_udf.c
    $WORK/usr/bin/searchd -c manticore.conf
    ;;
esac