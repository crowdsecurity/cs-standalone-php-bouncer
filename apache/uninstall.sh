#!/bin/bash

LIB_PATH="/usr/local/php/crowdsec/"
APACHE_CONFIG_FILE="/etc/apache2/conf-available/crowdsec_apache.conf"


sudo rm -rf "${LIB_PATH}"
sudo a2disconf crowdsec_apache
sudo rm "${APACHE_CONFIG_FILE}" || echo ""
sudo systemctl reload apache2

echo ""
echo "crowdsec-php-bouncer uninstalled successfully!"