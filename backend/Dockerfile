# PHP 8.4 — 基于自有基础镜像 king-shop/php:8.4.3-cli
FROM king-shop/php:8.4.3-cli

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
