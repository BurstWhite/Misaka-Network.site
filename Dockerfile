FROM node:20-alpine AS frontend-builder

WORKDIR /src/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM phpswoole/swoole:php8.2-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions one by one with lower optimization level for ARM64 compatibility
RUN CFLAGS="-O0" install-php-extensions pcntl && \
    CFLAGS="-O0 -g0" install-php-extensions bcmath && \
    install-php-extensions zip && \
    install-php-extensions redis && \
    apk --no-cache add shadow sqlite mysql-client mysql-dev mariadb-connector-c git patch supervisor redis caddy && \
    addgroup -S -g 1000 www && adduser -S -G www -u 1000 www && \
    (getent group redis || addgroup -S redis) && \
    (getent passwd redis || adduser -S -G redis -H -h /data redis)

WORKDIR /www

COPY .docker /

COPY . .

COPY --from=frontend-builder /src/theme/Misaka/assets /www/theme/Misaka/assets

ARG ADMIN_DIST_REPO=https://github.com/cedar2025/xboard-admin-dist.git
ARG ADMIN_DIST_COMMIT=ef5f43da335092cbff8fdf0ad7ff9b4d92d7d0d7

# The build context intentionally excludes .git. Fetch the pinned admin bundle
# directly so the final image stays reproducible without retaining Git history.
RUN rm -rf public/assets/admin && \
    git clone --filter=blob:none --no-checkout "${ADMIN_DIST_REPO}" public/assets/admin && \
    git -C public/assets/admin checkout "${ADMIN_DIST_COMMIT}" && \
    rm -rf public/assets/admin/.git

COPY .docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY .docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY .docker/php/zz-xboard.ini /usr/local/etc/php/conf.d/zz-xboard.ini

RUN composer install --no-cache --no-dev --no-security-blocking \
    && php artisan storage:link \
    && chown -R www:www /www \
    && chmod -R 775 /www \
    && mkdir -p /data \
    && chown redis:redis /data
    
ENV ENABLE_WEB=true \
    ENABLE_HORIZON=true \
    ENABLE_REDIS=true \
    ENABLE_WS_SERVER=true \
    ENABLE_CADDY=true

EXPOSE 7001
COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
