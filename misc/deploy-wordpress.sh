#!/usr/bin/env bash

WP_CORE_DIR='/srv/www/wordpress-default/public_html'

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

# http serves a single offer, whereas https serves multiple. we only want one
download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
if [[ -z "$LATEST_VERSION" ]]; then
    echo "Latest WordPress version could not be found"
    exit 1
fi
WP_TESTS_TAG="tags/$LATEST_VERSION"

set -ex

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	if [ $WP_VERSION == 'latest' ]; then
		local ARCHIVE_NAME='latest'
	else
		local ARCHIVE_NAME="wordpress-$WP_VERSION"
	fi

	download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR
}

add_db_user() {
    mysql -e "CREATE DATABASE wpTests"
    mysql -e "GRANT ALL PRIVILEGES ON wpTests.* to 'wp'@'localhost' IDENTIFIED BY 'wp'"
}

install_wp
add_db_user