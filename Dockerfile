FROM martkcz/alpine-php:8.0

RUN apk add -U --no-cache \
    supervisor \
    nginx \
    # Delete APK cache.
    && rm -rf /var/cache/apk/*

COPY rootfs /

RUN mkdir /var/opcache

## PHP builder
COPY ./builder /startup/builder

## bin
COPY ./bin /startup

WORKDIR /app

CMD ["/startup/run.sh"]

EXPOSE 8080
