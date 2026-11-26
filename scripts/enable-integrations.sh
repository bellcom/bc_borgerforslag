#!/usr/bin/env bash
# Enable only on a fresh test installation, no real calls until settings approval.
set -euo pipefail
cd "$(dirname "$0")/.."
vendor/bin/drush en os2web_nemlogin os2web_datalookup os2web_key os2web_audit -y
vendor/bin/drush cr
printf '%s\n' 'Modules enabled. Configure and verify real services separately; live remains disabled.'
