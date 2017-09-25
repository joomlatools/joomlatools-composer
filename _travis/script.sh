#!/bin/bash

set -e

echo "** Installing test extension"
composer require -v --working-dir=$DOCUMENTROOT/testsite --no-interaction joomlatools/composer-helloworld:dev-testbranch

# Verify if component file is present
[ -f $DOCUMENTROOT/testsite/administrator/components/com_helloworld/helloworld.php ] && true || false
grep -q "echo 'Hello World\!'" helloworld.php && true || false

# Test if the row exists in the database
COUNT=$(mysql -uroot -s -N -e "SELECT COUNT(extension_id) FROM sites_testsite.j_extensions WHERE element = 'com_helloworld';")
echo "Matched $COUNT rows\n"
"[ $COUNT -gt 0 ] && true || false"

echo "** Uninstalling test extension"
composer remove -v --working-dir=$DOCUMENTROOT/testsite --no-interaction joomlatools/composer-helloworld

# Verify if component file has been removed
[ ! -f $DOCUMENTROOT/testsite/administrator/components/com_helloworld/helloworld.php ] && true || false

# Ensure extensions table has been updated
COUNT=$(mysql -uroot -s -N -e "SELECT COUNT(extension_id) FROM sites_testsite.j_extensions WHERE element = 'com_helloworld';")
echo "Matched $COUNT rows\n"
[ $COUNT -eq 0 ] && true || false

