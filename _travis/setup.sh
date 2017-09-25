#!/bin/bash

set -e 
  
echo "Configuring PHP"
phpenv config-rm xdebug.ini
echo 'error_reporting = 22519' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini

echo "Create documentroot directory"
mkdir -p $DOCUMENTROOT
