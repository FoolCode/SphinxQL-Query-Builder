#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    $HOME/search/usr/bin/searchd -c sphinx.conf
    ;;
  SPHINX3)
    $HOME/search/sphinx-3.0.3/bin/searchd -c sphinx.conf
    ;;
  MANTICORE)
    $HOME/search/usr/bin/searchd -c sphinx.conf
    ;;
esac
