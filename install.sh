#!/bin/bash

LIB_PATH="/usr/local/php/crowdsec/"
APACHE_CONFIG_FILE="/etc/apache2/conf-available/crowdsec_apache.conf"
WEBSERVER=""

gen_apikey() {
    SUFFIX=`tr -dc A-Za-z0-9 </dev/urandom | head -c 8`
    API_KEY=`sudo cscli bouncers add crowdsec-php-bouncer-${SUFFIX} -o raw`
    cat ./settings.example.php | API_KEY=${API_KEY} envsubst '${API_KEY}' | sudo tee "${LIB_PATH}settings.php" >/dev/null
}

install() {
    sudo mkdir -p ${LIB_PATH}
    sudo cp settings.example.php "${LIB_PATH}settings.php"
    sudo cp crowdsec-php-bouncer.php ${LIB_PATH}
    sudo cp crowdsec-php-bouncer-refresh.php ${LIB_PATH}
    sudo cp -r vendor/ ${LIB_PATH}
}

install_apache() {
    sudo cp ./config/crowdsec_apache.conf ${APACHE_CONFIG_FILE}
    sudo a2enconf crowdsec_apache >/dev/null
    echo "crowdsec_apache apache2 configuration enabled"
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


echo "Installing crowdsec-php-bouncer"

if [ $(id -u) = 0 ]; then
    echo "Please run the install as non root user."
    exit 1
fi


install_dependency
install
gen_apikey

echo ""
echo "crowdsec-php-bouncer installed successfully!"
echo ""
echo "Please set the owner of '${LIB_PATH}' to www-data or to your webserver owner."
echo ""
echo "You can do it with:"
echo ""
echo "    sudo chown www-data ${LIB_PATH}"
echo ""

if [ "${WEBSERVER}" == "apache" ]; then
    install_apache
    echo ""
    echo "And reload apache2 service"
    echo ""
    echo "    sudo systemctl reload apache2"
else
    echo ""
    echo "Add the \"php_value auto_prepend_file '/usr/local/php/crowdsec/crowdsec-php-bouncer.php'\" to your .htacess file."
    echo ""
    echo "And reload your webserver."
fi



