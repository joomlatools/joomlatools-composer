#!/bin/bash

set -e 

echo "** Installing joomlatools/console"
composer global require --no-interaction joomlatools/console

composer global exec joomla -V
