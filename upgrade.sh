#!/bin/bash

LIB_PATH="/usr/local/php/crowdsec/"
APACHE_CONFIG_FILE="/etc/apache2/conf-available/crowdsec_apache.conf"
WEBSERVER=""


upgrade() {
    sudo mkdir -p ${LIB_PATH}
    sudo cp crowdsec-php-bouncer.php ${LIB_PATH}
    sudo cp crowdsec-php-bouncer-refresh.php ${LIB_PATH}
    sudo cp -r vendor/ ${LIB_PATH}
}

install_dependency() {
    composer install &>/dev/null
}

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


echo "Upgrading crowdsec-php-bouncer"

if [ $(id -u) = 0 ]; then
    echo "Please run the install as non root user."
    exit 1
fi


install_dependency
upgrade

echo "PHP Bouncer upgraded sucessfully"



