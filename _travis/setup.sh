#!/bin/bash

set -e 

echo "** Configuring PHP"
phpenv config-rm xdebug.ini
echo 'error_reporting = 22519' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini

echo "** Create documentroot directory"
mkdir -p $DOCUMENTROOT

echo "** Checkout test branch"
git checkout -b testbranch

# Based on the instructions from https://github.com/joomlatools/joomlatools-composer/wiki
echo "** Set up joomlatools/composer-helloworld"
git clone https://github.com/joomlatools/joomlatools-composer.git /tmp/joomlatools-composer-helloworld/

cd /tmp/joomlatools-composer-helloworld/
git checkout -b testbranch
cat >/tmp/joomlatools-composer-helloworld/composer.json <<EOL
{
    "require": {
         "joomlatools/composer": "dev-testbranch"
    }
}
EOL

cat /tmp/joomlatools-composer-helloworld/composer.json

git commit -a -m "Add test dependencies"

echo "** Set up test site"
joomla site:create --www=$DOCUMENTROOT --mysql-login="root" testsite
composer --working-dir=$DOCUMENTROOT/testsite config repositories.plugin vcs file:///$TRAVIS_BUILD_DIR
composer --working-dir=$DOCUMENTROOT/testsite config repositories.component vcs /tmp/joomlatools-composer-helloworld/
composer --working-dir=$DOCUMENTROOT/testsite config minimum-stability dev

cat $DOCUMENTROOT/testsite/composer.json

# Reset working directory
cd $TRAVIS_BUILD_DIR
