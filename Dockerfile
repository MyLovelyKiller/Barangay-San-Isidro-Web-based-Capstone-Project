FROM php:8.3-apache

ENV BMS_CLAMSCAN_PATH=/usr/bin/clamscan

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        clamav \
        clamav-freshclam \
        libcurl4-openssl-dev \
        libonig-dev \
    && docker-php-ext-install curl mbstring mysqli \
    && a2enmod headers rewrite \
    && freshclam --stdout --verbose \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/bms-entrypoint
COPY docker-php.ini /usr/local/etc/php/conf.d/bms.ini

RUN sed -i 's/\r$//' /usr/local/bin/bms-entrypoint \
    && chmod +x /usr/local/bin/bms-entrypoint \
    && a2enmod setenvif

ENV PORT=80
EXPOSE 80

CMD ["bms-entrypoint"]
