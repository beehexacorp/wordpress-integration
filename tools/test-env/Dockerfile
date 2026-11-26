# PHP CLI image with mysqli, used for the WordPress site, WP-CLI and the fake HexaSync server.
# Build with --build-arg PHP_IMAGE=php:8.6-cli to try a newer PHP.
ARG PHP_IMAGE=php:8.3-cli
FROM ${PHP_IMAGE}
RUN docker-php-ext-install mysqli
