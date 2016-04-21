#!/usr/bin/env bash

# set composer token for github
php -n /usr/local/bin/composer config -g github-oauth.github.com "$GITHUB_TOKEN"

# force https
php -n /usr/local/bin/composer config --global github-protocols https

if [ "$1" = "bumper" ]; then
    # run composer
    php app/console composer:update "$GITHUB_TOKEN" "${@:2}"
else
    # Run initial command from arguments
    "$@"
fi;
