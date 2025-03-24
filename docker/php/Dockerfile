ARG ALPINE_VERSION=3.20
ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION}

ENV TZ=Europe/Paris
ENV WORKDIR=/var/www/app.dev

RUN apk add --update --no-cache \
  bash \
  tzdata \
  libxml2-dev \
  libpng-dev \
  libjpeg-turbo-dev \
  freetype-dev \
  postgresql-dev \
  libzip-dev \
  libxslt-dev \
  librdkafka-dev \
  oniguruma-dev \
  linux-headers \
  $PHPIZE_DEPS && \
  rm -rf /var/cache/apk/*

# Install xdebug
RUN pecl install -f xdebug \
    && docker-php-ext-enable xdebug \
    && ls /etc \
    # ensure that xdebug is not enabled by default
    && rm -f /etc/php8/conf.d/00_xdebug.ini

# Install rdkafka
RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka

RUN docker-php-ext-configure soap --enable-soap
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
  -j$(nproc) gd \
  #  iconv \
  bcmath \
  xml \
  soap \
  mbstring \
  zip \
  opcache \
  xsl \
  exif \
  soap

COPY ./docker/php/conf.d ${PHP_INI_DIR}/conf.d

WORKDIR ${WORKDIR}

COPY ./ /var/www/app.dev/

# Install composer
RUN ./docker/php/install-composer.sh

EXPOSE 9000

COPY ./docker/php/entrypoint.sh /usr/bin/docker-entrypoint
RUN ["chmod", "+x", "/usr/bin/docker-entrypoint"]

CMD [ "docker-entrypoint" ]
