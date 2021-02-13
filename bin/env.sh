#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    WORK=$HOME/search
    ;;
  SPHINX3)
    WORK=$HOME/search/sphinx-3.0.3
    ;;
  MANTICORE)
    WORK=$HOME/search
    ;;
esac