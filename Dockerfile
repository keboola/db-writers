ARG PHP_VERSION=8.2
ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive

FROM php:${PHP_VERSION}-cli AS base
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

FROM php:${PHP_VERSION}-cli-buster AS base-buster
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

FROM base AS lib-db-writer-config
ENV APP_NAME=db-writer-config
ENV APP_HOME=/code/libs/${APP_NAME}
ARG COMPOSER_MIRROR_PATH_REPOS=1

WORKDIR ${APP_HOME}

COPY libs/${APP_NAME}/docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        locales \
        unzip \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

## Composer - deps always cached unless changed
# First copy only composer files
COPY libs/${APP_NAME}/composer.* ./

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY libs/${APP_NAME} ./

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["php", "/code/src/run.php"]

FROM base AS lib-db-writer-adapter
ARG COMPOSER_MIRROR_PATH_REPOS=1
ENV APP_NAME=db-writer-adapter
ENV APP_HOME=/code/libs/${APP_NAME}

WORKDIR ${APP_HOME}

COPY libs/${APP_NAME}/docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh
COPY libs/${APP_NAME}/docker/MariaDB_odbc_driver_template.ini /etc/MariaDB_odbc_driver_template.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        locales \
        unzip \
        unixodbc \
        unixodbc-dev \
        odbc-mariadb \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh \
    && odbcinst -i -d -f /etc/MariaDB_odbc_driver_template.ini

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

# PDO mysql
RUN docker-php-ext-install pdo_mysql

# PHP ODBC
# https://github.com/docker-library/php/issues/103#issuecomment-353674490
RUN set -ex; \
    docker-php-source extract; \
    { \
        echo '# https://github.com/docker-library/php/issues/103#issuecomment-353674490'; \
        echo 'AC_DEFUN([PHP_ALWAYS_SHARED],[])dnl'; \
        echo; \
        cat /usr/src/php/ext/odbc/config.m4; \
    } > temp.m4; \
    mv temp.m4 /usr/src/php/ext/odbc/config.m4; \
    docker-php-ext-configure odbc --with-unixODBC=shared,/usr; \
    docker-php-ext-install odbc; \
    docker-php-source delete

## Composer - deps always cached unless changed
# First copy only composer files
COPY libs/${APP_NAME}/composer.* ./

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN --mount=type=bind,source=libs/db-writer-config,target=/code/libs/db-writer-config \
    composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY libs/${APP_NAME} ./

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["composer", "ci"]

FROM base AS lib-db-writer-common
ENV APP_NAME=db-writer-common
ARG COMPOSER_MIRROR_PATH_REPOS=1
ENV APP_HOME=/code/libs/${APP_NAME}

WORKDIR ${APP_HOME}

COPY libs/${APP_NAME}/docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh

# Install dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    locales \
    curl  \
    git  \
    bzip2  \
    time  \
    libzip-dev \
    ssh \
    openssl  \
    unzip  \
    procps \
    libicu-dev \
    && rm -r /var/lib/apt/lists/* \
    && sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
    && locale-gen \
    && chmod +x /tmp/composer-install.sh \
    && /tmp/composer-install.sh

# PDO mysql
RUN docker-php-ext-install pdo_mysql

# INTL
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl

## Composer - deps always cached unless changed
# First copy only composer files
COPY libs/${APP_NAME}/composer.* ./

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN --mount=type=bind,source=libs/db-writer-adapter,target=/code/libs/db-writer-adapter \
    --mount=type=bind,source=libs/db-writer-config,target=/code/libs/db-writer-config \
    composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY libs/${APP_NAME} ./

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

