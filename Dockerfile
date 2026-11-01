FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_mysql intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as a non-root user with the host UID/GID, so files in vendor/ and var/ belong to the host user.
ARG UID=1000
ARG GID=1000

# libcap provides setcap: the non-root user may bind port 80/443 inside the container.
# git is needed by "composer recipes:update" (Symfony Flex applies recipe changes as a git patch).
RUN apk add --no-cache libcap git \
    && addgroup -g ${GID} app \
    && adduser -D -u ${UID} -G app app \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /data/caddy /config/caddy

USER app
