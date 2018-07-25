FROM php:7.2

# Install system packages
RUN apt-get update \
    && apt-get install -qqy git unzip libpng-dev mariadb-server

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) gd mysqli

# Configure php
RUN echo "date.timezone = UTC" >> /usr/local/etc/php/php.ini

# Configure Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer global require phpunit/phpunit:6.* \
    && composer global require lucatume/wp-browser \
    && ln -s ~/.composer/vendor/bin/codecept /usr/local/bin/codecept

# Install CodeSniffer
RUN pear install PHP_CodeSniffer

# WordPress Coding Standards
RUN git clone -b master https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git wpcs \
    && phpcs --config-set installed_paths /wpcs
