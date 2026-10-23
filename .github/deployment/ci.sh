#!/usr/bin/env bash
set -euo pipefail
# Runs only on a disposable runner with a disposable MySQL service.
find app config database public scripts tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php -l index.php
php scripts/build-hostinger-package.php
python3 - <<'PY'
import pathlib, secrets
root = pathlib.Path.cwd()
assert not (root / '.env').exists(), 'CI must never reuse an existing environment'
(root / '.env').write_text('\n'.join([
    'APP_ENV=testing', 'APP_DEBUG=false', 'APP_KEY=' + secrets.token_hex(32),
    'APP_URL=http://127.0.0.1:8080', 'DB_HOST=127.0.0.1', 'DB_PORT=3306',
    'DB_NAME=axa_ci', 'DB_USER=root', 'DB_PASSWORD=disposable-ci-only',
    'DB_MIGRATION_USER=root', 'DB_MIGRATION_PASSWORD=disposable-ci-only',
    'TRUST_PROXY_HEADERS=false',
]) + '\n')
pathlib.Path('/tmp/axa-ci.conf').write_text(f'''
Listen 8080
<VirtualHost *:8080>
  DocumentRoot "{root}"
  <Directory "{root}">
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>
''')
PY
# Apache runs as the runner, so private fixtures stay accessible without broad permissions.
sudo sed -i "s/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=$(id -un)/;s/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=$(id -gn)/" /etc/apache2/envvars
sudo cp /tmp/axa-ci.conf /etc/apache2/sites-available/axa-ci.conf
sudo a2enmod rewrite headers
sudo a2ensite axa-ci
sudo apache2ctl configtest
sudo service apache2 restart
php scripts/migrate.php
php scripts/seed.php
php tests/test-cli-password.php
php tests/test-security-harness.php
php tests/test-production-simulation.php
php scripts/check-hostinger-http.php http://127.0.0.1:8080
