#!/usr/bin/env bash
# Run as a script, in project root; never source this file.
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ ${1:-} != --fresh ]]; then echo 'Usage: scripts/install.sh --fresh [--demo] (empty database only)' >&2; exit 2; fi
: "${BC_DB_URL:?Set BC_DB_URL to a NEW empty Drupal database URL}"
: "${BC_ADMIN_PASSWORD:?Set a strong BC_ADMIN_PASSWORD}"
: "${BC_SITE_URI:?Set BC_SITE_URI to the site URL}"
if [[ -f web/sites/default/settings.php ]]; then echo 'FEJL: settings.php findes. Eksisterende site ændres ikke.' >&2; exit 1; fi
if ! php -r 'exit(PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80500 ? 0 : 1);'; then echo 'FEJL: PHP 8.4 CLI kræves.' >&2; exit 1; fi
composer validate --no-check-lock
if [[ -f composer.lock ]]; then composer install --no-interaction --prefer-dist; else composer update --no-interaction --prefer-dist; fi
# Use PDO to refuse an existing database; credentials remain in environment.
php scripts/check-empty-db.php
vendor/bin/drush --uri="$BC_SITE_URI" site:install standard --db-url="$BC_DB_URL" --site-name='BC Borgerforslag' --account-name=bc_admin --account-pass="$BC_ADMIN_PASSWORD" -y
vendor/bin/drush --uri="$BC_SITE_URI" en bc_borgerforslag admin_toolbar admin_toolbar_tools -y
vendor/bin/drush --uri="$BC_SITE_URI" theme:enable gin -y
vendor/bin/drush --uri="$BC_SITE_URI" cset system.theme admin gin -y
vendor/bin/drush --uri="$BC_SITE_URI" cset system.site page.front /borgerforslag -y
vendor/bin/drush --uri="$BC_SITE_URI" cset system.date timezone.default Europe/Copenhagen -y
if [[ ${2:-} == --demo ]]; then
  vendor/bin/drush --uri="$BC_SITE_URI" php:script "$PWD/scripts/setup-demo.php"
fi
vendor/bin/drush --uri="$BC_SITE_URI" cr
vendor/bin/drush --uri="$BC_SITE_URI" status
printf '\nInstalleret: %s/borgerforslag\n' "$BC_SITE_URI"
