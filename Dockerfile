FROM loliee/docker-php:5.6
MAINTAINER Arnaud VEBER <arnaud@veber.io>

# install composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

# create target directory
RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app

# copy application
COPY ./ /usr/src/app/

# install dependencies
RUN php -n -dmemory_limit=-1 /usr/local/bin/composer install

# entrypoint
ADD docker/entrypoint.sh /usr/src/entrypoint.sh
RUN chmod +x /usr/src/entrypoint.sh

ENTRYPOINT ["/usr/src/entrypoint.sh"]
