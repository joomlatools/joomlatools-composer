#!/bin/bash

set -e 

echo "Installing joomlatools/console"
composer global require --no-interaction joomlatools/console
joomla -V
