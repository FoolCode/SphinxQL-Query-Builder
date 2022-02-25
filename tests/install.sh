#!/bin/sh

case $SEARCH_BUILD in
  SPHINX2)
    wget --quiet http://sphinxsearch.com/files/sphinx-2.2.11-release.tar.gz
    tar zxvf sphinx-2.2.11-release.tar.gz
    cd sphinx-2.2.11-release
    ./configure --prefix=/usr/local/sphinx
    sudo make && sudo make install
    ;;
  SPHINX3)
    wget --quiet http://sphinxsearch.com/files/sphinx-3.0.3-facc3fb-linux-amd64.tar.gz
    tar zxvf sphinx-3.0.3-facc3fb-linux-amd64.tar.gz
    ;;
  MANTICORE)
    wget --quiet -O search.deb https://github.com/manticoresoftware/manticoresearch/releases/download/2.6.3/manticore_2.6.3-180328-cccb538-release-stemmer.trusty_amd64-bin.deb
    dpkg -x search.deb .
    ;;
esac
