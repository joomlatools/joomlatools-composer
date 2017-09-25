#!/bin/bash

set -e 

echo "Updating Composer"
composer self-update && composer --version
composer -V

echo "Installing joomlatools/console"
composer global require --no-interaction joomlatools/console
joomla -V