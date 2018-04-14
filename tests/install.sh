#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    wget --quiet -O search.deb https://ppa.launchpad.net/builds/sphinxsearch-rel22/ubuntu/sphinxsearch_2.2.11-release-0ubuntu12~trusty_amd64.deb 
    dpkg -x search.deb .
    ;;
  SPHINX3)
    wget --quiet http://sphinxsearch.com/files/sphinx-3.0.3-facc3fb-linux-amd64.tar.gz
    tar zxvf sphinx-3.0.3-facc3fb-linux-amd64.tar.gz
    ;;
  MANITCORE)
    wget --quiet -O search.deb https://github.com/manticoresoftware/manticoresearch/releases/download/2.6.3/manticore_2.6.3-180328-cccb538-release-stemmer.trusty_amd64-bin.deb
    dpkg -x search.deb .
    ;;
esac
