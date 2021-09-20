#!/bin/bash

LIB_PATH="/usr/local/php/crowdsec/"

sudo rm -rf "${LIB_PATH}"

echo ""
echo "crowdsec-php-bouncer uninstalled successfully!"
echo ""
echo "Remove the \"php_value auto_prepend_file '/usr/local/php/crowdsec/crowdsec-php-bouncer.php'\" from your .htacess file"