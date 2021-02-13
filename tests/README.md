SphinxQL Query Builder Unit Tests
=================================

## Install

Choose the version you want to use (e.g. Sphinx 2, Sphinx 3 or Manticore).
Then set an environment variable:
 - Sphinx 2: Typ `SEARCH_BUILD=SPHINX2` in the console
 - Sphinx 3: Typ `SEARCH_BUILD=SPHINX3` in the console
 - Manticore: Typ `SEARCH_BUILD=MANTICORE` in the console

Go to the location where you want to install Sphinx Search (e.g. `/home/user/search`).
From that location run the `install.sh` file that is in `bin/`.
Sphinx will be installed in your current folder.

## Run

After installing, go to the `bin/` directory.
First set the `$WORK` environment variable to the location you ran `install.sh` in (e.g. `/home/user/search`, then do `WORK=/home/user/search`).
In case of Sphinx 3, append `/sphinx-3.0.3` to it.
Then run `run.sh`.