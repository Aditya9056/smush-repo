# Misc utilities

Several utilities that will help automate the build and development process for Smush.

Smush uses Codeception to run acceptance and functional tests, as well as WP unit tests. Most of the tests require a valid
WordPress install to be present on the server. Instead of manually updating the Docker image for Bitbucket Pipelines, we
will be downloading the latest version with the following script. 

## deploy-wordpress.sh

Script to install the latest WordPress version in the Docker image during Bitbucket Pipelines execution.

Installation path: `/srv/www/wordpress-default/public_html`

This path needs to correlate to the `WP_ROOT_FOLDER` constant in the `.env` file for Codeception unit tests to properly work.

## deploy-to-svn.sh

Script that will fetch latest wp.org release to `./build/smush-svn` directory. Auto merge all changes from the current (new)
release and show next steps for pushing to WordPress SVN.

## Docerkfile

This file is used to generate a Docker image used in Bitbucket Pipelines.

Contains:
* PHP 7.2 (with GD and MySQLi extension)
* MariaDB 10.1
* PHPUnit 6.5
* Codeception 2.2
* Composer 1.6
* PHP Codesniffer + WordPress Coding Standards

Place the file in a directory, inside the directory execute the following command to build the image:

`# docker build -t incusb/codeception .` (don't forget the dot at the end)

Push the image to a desired repo, tagging it as a latest image.

`# docker push incsub/codeception:latest` 

Set the `image` variable to the built image name in the `bitbucket-pipelines.yml` file.
