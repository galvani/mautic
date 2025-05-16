#syntax=docker/dockerfile:1

# Versions
FROM dunglas/frankenphp:1-php8.3 AS frankenphp_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

VOLUME /app/var/

# Build argument to determine the role of the container
ARG CONTAINER_ROLE=web
ENV CONTAINER_ROLE=${CONTAINER_ROLE}

# Display PHP version and modules to understand the environment
RUN php -v && php -m

RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    file \
    gettext \
    git \
    unzip \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libkrb5-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    libxslt-dev \
    libmagickwand-dev \
    zlib1g-dev \
    libmemcached-dev \
    nodejs \
    npm \
    default-mysql-client \
    librabbitmq-dev \
    libssh-dev \
    cron \
    curl \
    libc-client-dev \
    libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
        bcmath \
        calendar \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        soap \
        sockets \
        xml \
        zip \
        xsl \
        redis \
        amqp \
        apcu \
        memcached \
        imagick \
        imap \
    ;
# Set recommended PHP settings
RUN { \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=24'; \
        echo 'opcache.max_accelerated_files=100000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=300'; \
        echo 'upload_max_filesize=100M'; \
        echo 'post_max_size=100M'; \
    } > /usr/local/etc/php/conf.d/99-mautic-recommended.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy configuration files from .docker directory
COPY .docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY .docker/crontab /etc/crontabs/www-data
RUN chmod +x /usr/local/bin/docker-entrypoint

# Copy only composer files first
COPY composer.json composer.lock /app/

# Now copy the application code
COPY --chown=www-data:www-data . /app
WORKDIR /app

# Install Mautic dependencies
# PROD: RUN cd /app && composer install --no-dev --optimize-autoloader
#RUN composer install

# Install NPM dependencies and generate assets
#RUN npm ci --prefer-offline --no-audit && \
#    npx patch-package && \
#    bin/console mautic:assets:generate

# Set proper permissions - with the correct directory structure
RUN mkdir -p /app/var/cache /app/var/logs /app/var/tmp /app/var/spool /app/media/files /app/media/images
RUN chown -R www-data:www-data /app/var /app/media
RUN chmod -R 775 /app/var /app/media

COPY .docker/Caddyfile /etc/caddy/Caddyfile

ENV MAX_REQUESTS=1000
ENV MAUTIC_CUSTOM_DEV_HOSTS='["localhost","127.0.0.1","172.18.0.1"]'

EXPOSE 80 443 443/udp
ENTRYPOINT ["docker-entrypoint"]
HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

# DEV stuff
RUN apt update && apt install -y --no-install-recommends vim && rm -rf /var/lib/apt/lists/*
