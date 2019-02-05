#!/usr/bin/env bash

if [ $# -lt 1 ]; then
	echo "usage: $0 --link=[true|false]"
	exit 1
fi

DB_NAME="wpTests"
DB_USER="wp"
DB_PASS="wp"
SYMBOLIC_LINK=$1

WP_TESTS_DIR='/tmp/wordpress-tests-lib'
WP_CORE_DIR='/tmp/wordpress/'

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

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR"wp-content/db.php
}


install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR ]; then
		# set up testing suite
		mkdir -p $WP_TESTS_DIR
		svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ $WP_TESTS_DIR/includes
		svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ $WP_TESTS_DIR/data
	fi

	cd $WP_TESTS_DIR

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi
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

link_to_plugin_folder() {
	if [ $SYMBOLIC_LINK == '--link=true' ]; then
    	ln -s `pwd` "$WP_CORE_DIR"wp-content/plugins/wp-smushit
    	echo "Symbolic link created"
	else
		echo "Symbolic link skipped with flag"
	fi
}

install_wp
link_to_plugin_folder
install_test_suite
update_wp_config
add_db_user
