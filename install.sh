#!/bin/bash

LIB_PATH="/usr/local/php/crowdsec/"
APACHE_CONFIG_FILE="/etc/apache2/conf-available/crowdsec_apache.conf"

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
    sudo cp ./config/crowdsec_apache.conf ${APACHE_CONFIG_FILE}
    sudo a2enconf crowdsec_apache
}

install_dependency() {
    composer install &>/dev/null
}


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
echo "And reload apache2 service"
echo ""
echo "    sudo systemctl reload apache2"