FROM ubuntu:18.04

# Install PHP 7.2
RUN apt-get update && apt-get install -qqy software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN export DEBIAN_FRONTEND=noninteractive DEBCONF_NONINTERACTIVE_SEEN=true
RUN apt-get install -y tzdata --no-install-recommends
RUN ln -fs /usr/share/zoneinfo/Europe/Moscow /etc/localtime && \
    dpkg-reconfigure --frontend noninteractive tzdata
# gcc libxml2-dev add about 200 Mb, but are needed for phpfarm
RUN apt-get install -y nano wget curl unzip subversion git php-pear libpng-dev gcc libxml2-dev \
    php7.2 php7.2-mysql php7.2-gd php7.2-dom php7.2-mbstring php7.2-curl php-xdebug --no-install-recommends

# Configure php
RUN echo "date.timezone = UTC" >> /etc/php/7.2/apache2/php.ini

# Install MariaDB
RUN apt-get install -qqy mariadb-server --no-install-recommends

####
# Add phpfarm with all the php versions
####
RUN git clone https://github.com/fpoirotte/phpfarm.git /phpfarm
WORKDIR /phpfarm/src
# PHP 5.2.17 needs a patch, so we manually download and apply patch before actual install
RUN wget https://museum.php.net/php5/php-5.2.17.tar.bz2 -O bzips/php-5.2.17.tar.bz2 && \
    tar -xjvf bzips/php-5.2.17.tar.bz2 php-5.2.17

WORKDIR /phpfarm/src/php-5.2.17
RUN wget http://storage.googleapis.com/google-code-attachments/php52-backports/issue-16/comment-2/libxml29_compat.patch && \
    patch -p0 < libxml29_compat.patch

WORKDIR /phpfarm/src
RUN ./main.sh 5.2.17 && \
    ./main.sh 5.3.29 && \
    ./main.sh 5.4.45 && \
    ./main.sh 5.5.38 && \
    ./main.sh 5.6.38 && \
    ./main.sh 7.0.32 && \
    ./main.sh 7.1.22

# Configure Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require --optimize-autoloader "hirak/prestissimo"
RUN composer global require --prefer-dist --optimize-autoloader "lucatume/wp-browser"
RUN ln -s ~/.composer/vendor/bin/codecept /usr/local/bin/codecept

# Install CodeSniffer
RUN pear install PHP_CodeSniffer

# WordPress Coding Standards
RUN git clone -b master https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git wpcs
RUN phpcs --config-set installed_paths /wpcs

# Cleanup to save space
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    rm -rf /phpfarm/src && \
    #rm -rf /usr/lib/x86_64-linux-gnu && \
    rm -rf ./root/.composer/cache

# Prepare host-volume working directory
RUN mkdir /project
WORKDIR /project