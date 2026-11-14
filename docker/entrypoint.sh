#!/bin/sh
set -e

# Espera o MariaDB aceitar conexões e cria admin + dados de demonstração (apenas na 1ª vez)
php /var/www/html/database/seed.php

exec "$@"
