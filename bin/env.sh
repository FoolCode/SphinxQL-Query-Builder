#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    export WORK=$HOME/search
    ;;
  SPHINX3)
    export WORK=$HOME/search/sphinx-3.0.3
    ;;
  MANTICORE)
    export WORK=$HOME/search
    ;;
esac