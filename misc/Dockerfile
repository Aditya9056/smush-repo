FROM ubuntu:18.04

# Install PHP 7.2
RUN apt-get update && apt-get install -qqy software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN export DEBIAN_FRONTEND=noninteractive DEBCONF_NONINTERACTIVE_SEEN=true
RUN apt-get install -y tzdata
RUN ln -fs /usr/share/zoneinfo/Europe/Moscow /etc/localtime \
    && dpkg-reconfigure --frontend noninteractive tzdata
RUN apt-get install -y curl php-pear git unzip libpng-dev \
    php7.2 php7.2-mysql php7.2-gd php7.2-dom php7.2-mbstring php7.2-curl

# Install MariaDB
RUN apt-get update && apt-get install -qqy mariadb-server

# Configure Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require lucatume/wp-browser
RUN ln -s ~/.composer/vendor/bin/codecept /usr/local/bin/codecept

# Install CodeSniffer
RUN pear install PHP_CodeSniffer

# WordPress Coding Standards
RUN git clone -b master https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git wpcs
RUN phpcs --config-set installed_paths /wpcs

# Prepare host-volume working directory
RUN mkdir /project
WORKDIR /project