#!/usr/bin/env bash

WP_CORE_DIR='/srv/www/wordpress-default/public_html'

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

set -ex

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	download https://wordpress.org/latest.tar.gz  /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR
}

add_db_user() {
    mysql -e "CREATE DATABASE wpTests"
    mysql -e "GRANT ALL PRIVILEGES ON wpTests.* to 'wp'@'localhost' IDENTIFIED BY 'wp'"
}

install_wp
add_db_user