#!/usr/bin/env bash
# One-shot setup for a fresh clone of this repo on a new machine/server:
# copies config samples into place, fills in DB/site settings, creates the
# database, imports db/schema.sql if present, and clears the Smarty cache.
#
# Usage: bin/setup.sh
set -euo pipefail
cd "$(dirname "$0")/.."

prompt() {
	local label="$1" default="$2" reply
	read -rp "$label [$default]: " reply || true
	echo "${reply:-$default}"
}

# BSD sed (macOS) requires -i '' ; GNU sed (Linux) errors on that form.
sed_inplace() {
	if sed --version >/dev/null 2>&1; then
		sed -i "$@"
	else
		sed -i '' "$@"
	fi
}

DB_HOST=$(prompt "DB host" "localhost")
DB_PORT=$(prompt "DB port" "3306")
DB_NAME=$(prompt "DB name" "vtigercrm")
DB_USER=$(prompt "DB username" "root")
DB_PASS=$(prompt "DB password" "")
SITE_URL=$(prompt "Site URL" "http://localhost/vtiger/")
ROOT_DIR=$(prompt "Install root directory" "$(pwd)/")

for f in config.inc.php config.csrf-secret.php kcfinder/config.php; do
	if [ ! -f "$f" ] && [ -f "$f.sample" ]; then
		cp "$f.sample" "$f"
		echo "Created $f from $f.sample"
	fi
done

# cache/, logs/ and test/templates_c/ are entirely gitignored (no tracked
# files), so a fresh clone never has them. Several code paths (e.g. Module
# Designer's temp dir) do a non-recursive mkdir() that fails outright if the
# parent is missing, and Smarty needs its compile dir to exist up front.
mkdir -p cache/images cache/import cache/upload cache/tempModuleDesigner cache/Connector logs test/templates_c/v7
chmod -R 755 cache logs test/templates_c
echo "Created cache/, logs/ and test/templates_c/ runtime directories"

APP_KEY=$(php -r "echo bin2hex(random_bytes(16));")
CSRF_SECRET=$(php -r "echo bin2hex(random_bytes(20));")

sed_inplace \
	-e "s/\$dbconfig\['db_server'\] = '[^']*';/\$dbconfig['db_server'] = '${DB_HOST}';/" \
	-e "s/\$dbconfig\['db_port'\] = '[^']*';/\$dbconfig['db_port'] = ':${DB_PORT}';/" \
	-e "s/\$dbconfig\['db_username'\] = '[^']*';/\$dbconfig['db_username'] = '${DB_USER}';/" \
	-e "s/\$dbconfig\['db_password'\] = '[^']*';/\$dbconfig['db_password'] = '${DB_PASS}';/" \
	-e "s/\$dbconfig\['db_name'\] = '[^']*';/\$dbconfig['db_name'] = '${DB_NAME}';/" \
	-e "s#\\\$site_URL = '[^']*';#\$site_URL = '${SITE_URL}';#" \
	-e "s#\\\$root_directory = '[^']*';#\$root_directory = '${ROOT_DIR}';#" \
	-e "s/\$application_unique_key = '[^']*';/\$application_unique_key = '${APP_KEY}';/" \
	config.inc.php

printf '<?php $secret = "%s";\n' "$CSRF_SECRET" > config.csrf-secret.php
echo "Wrote config.inc.php and config.csrf-secret.php"

mysql_args=(-h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
if [ -n "$DB_PASS" ]; then
	mysql_args+=(-p"$DB_PASS")
fi

mysql "${mysql_args[@]}" -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4;"

if [ -f db/schema.sql ]; then
	mysql "${mysql_args[@]}" "$DB_NAME" < db/schema.sql
	echo "Imported db/schema.sql into ${DB_NAME}"
	echo "The imported admin login has a placeholder credential and cannot log in yet."
	echo "Run bin/reset-admin-login.sh to set a real one."
else
	echo "No db/schema.sql found - run vtiger's own install wizard against this database instead."
fi

rm -rf test/templates_c/v7/*
echo "Cleared Smarty compile cache"

echo "Setup complete."
