#!/usr/bin/env bash
# Optional OS2Forms dependency set. Run in a separate branch after base install.
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ -n $(git status --porcelain -- composer.json composer.lock) ]]; then echo 'FEJL: Commit Composer-filer før ændringer.' >&2; exit 1; fi
composer require --no-update 'os2forms/os2forms:dev-develop#e4430d2fe1bd1773fdb4f3d1f13bb080489c52bf' 'os2web/os2web_simplesaml:dev-master#c85cdb6967c0b298ca3cd0a49947ee3828867f48'
# Do not auto-trust dependency plugins or force incompatible packages.
composer update --with-all-dependencies --dry-run --no-interaction
printf '%s\n' 'Dry-run complete. Review package/plugin/patch requirements and then run composer update --with-all-dependencies.'
