#!/bin/bash

set -e

FILE="$DOCUMENTROOT/testsite/administrator/components/com_helloworld/helloworld.php"
DB="sites_testsite.j_extensions"

if [[ "$REPO" == *"joomlatools/joomlatools-platform"* ]]; then
  FILE="$DOCUMENTROOT/testsite/app/administrator/components/com_helloworld/helloworld.php"
  DB="sites_testsite.extensions"
fi

echo "** Installing test extension"
composer require -vv --working-dir=$DOCUMENTROOT/testsite --no-interaction joomlatools/composer-helloworld:dev-testbranch

# Verify if component file is present
echo "** Looking for helloworld.php"

echo "** Verifying helloworld.php contents"
if [ ! -f $FILE ]; then
    echo "$FILE does not exist"
    exit 1
fi

if ! grep -q "echo 'Hello World\!'" $FILE; then
    echo "$FILE does not contain string"
    exit 1
fi

# Test if the row exists in the database
echo "** Verifying extensions row in database"

COUNT=$(mysql -uroot -s -N -e "SELECT COUNT(extension_id) FROM $DB WHERE element = 'com_helloworld';")
echo "Matched $COUNT rows"

if [ $COUNT -le 0 ]; then
   exit 1
fi
