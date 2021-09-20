#!/bin/bash

LIB_PATH="/usr/local/php/crowdsec/"
APACHE_CONFIG_FILE="/etc/apache2/conf-available/crowdsec_apache.conf"
WEBSERVER=""

while [[ $# -gt 0 ]]
do
    key="${1}"
    case ${key} in
    --apache)
        WEBSERVER="apache"
        shift #past argument
        ;;
    esac
done



sudo rm -rf "${LIB_PATH}"

echo "crowdsec-php-bouncer uninstalled successfully!"

if [ "${WEBSERVER}" == "apache" ]; then
    sudo a2disconf crowdsec_apache >/dev/null && echo "crowdsec_apache configuration for apache2 disabled."
    sudo rm "${APACHE_CONFIG_FILE}" 2> /dev/null
    sudo systemctl reload apache2
else
    echo ""
    echo "Remove the \"php_value auto_prepend_file '/usr/local/php/crowdsec/crowdsec-php-bouncer.php'\" from your .htacess file"
fi

