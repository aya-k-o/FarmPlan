FROM php:8.2-apache

# PDO MySQL 拡張を有効化（PHPからMySQLに接続するために必要）
RUN docker-php-ext-install pdo pdo_mysql

# Apache の mod_rewrite を有効化
RUN a2enmod rewrite
