#!/bin/bash

set -e 

echo "Configuring PHP"
phpenv config-rm xdebug.ini
echo 'error_reporting = 22519' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini

echo "Create documentroot directory"
mkdir -p $DOCUMENTROOT

echo "Checkout test branch"
git checkout -b testbranch

# Based on the instructions from https://github.com/joomlatools/joomlatools-composer/wiki
echo "Set up joomlatools/composer-helloworld"
git clone git@github.com:joomlatools/joomlatools-composer-helloworld.git /tmp/joomlatools-composer-helloworld/

cd /tmp/joomlatools-composer-helloworld/
git checkout -b testbranch
cat >composer.json <<EOL
{
    "require": {
         "joomlatools/composer": "dev-testbranch"
    }
}
EOL
git commit -a -m "Add test dependencies"

echo "Set up test site"
joomla site:create --www=$DOCUMENTROOT --mysql-login="root" composer
composer --working-dir=$DOCUMENTROOT/composer config repositories.plugin vcs file:///$TRAVIS_BUILD_DIR
composer --working-dir=$DOCUMENTROOT/composer config repositories.component vcs /tmp/joomlatools-composer-helloworld/
composer --working-dir=$DOCUMENTROOT/composer config minimum-stability dev

# Reset working directory
cd $TRAVIS_BUILD_DIR
