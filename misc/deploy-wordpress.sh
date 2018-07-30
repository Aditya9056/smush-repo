#!/usr/bin/env bash

DB_NAME="wpTests"
DB_USER="wp"
DB_PASS="wp"
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

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

add_db_user() {
    mysql -e "CREATE DATABASE $DB_NAME"
    mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* to '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS'"
}

update_wp_config() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	cd $WP_CORE_DIR

	if [ ! -f wp-tests-config.php ]; then
        cp "$WP_CORE_DIR"/wp-config-sample.php "$WP_CORE_DIR"/wp-config.php
        sed $ioption "s/database_name_here/$DB_NAME/" "$WP_CORE_DIR"/wp-config.php
        sed $ioption "s/username_here/$DB_USER/" "$WP_CORE_DIR"/wp-config.php
        sed $ioption "s/password_here/$DB_PASS/" "$WP_CORE_DIR"/wp-config.php
    fi
}

install_wp
add_db_user
update_wp_config