#!/bin/bash

set -e 

echo $PATH

echo "** Installing joomlatools/console"
composer global require --no-interaction joomlatools/console

ls -lah /home/travis/.composer/vendor/bin/

joomla -V
