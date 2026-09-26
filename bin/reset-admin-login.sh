#!/usr/bin/env bash
# Resets an EXISTING admin login's password - the same fix vtiger's own
# community help threads give for a lost admin password, just scripted:
#   UPDATE vtiger_users SET user_password = ... WHERE user_name = 'admin';
# Uses PHP's own password_hash(), matching modules/Users/Users.php's PHASH
# path, so vtiger accepts it on the next login. Touches only the password
# fields - never creates or otherwise alters the user row, so nothing else
# (access_key, preferences, etc.) is affected.
#
# Usage: bin/reset-admin-login.sh [db_name] [db_user] [db_host] [db_port]
set -euo pipefail
cd "$(dirname "$0")/.."

DB_NAME="${1:-vtigercrm}"
DB_USER="${2:-root}"
DB_HOST="${3:-localhost}"
DB_PORT="${4:-3306}"

read -rp "DB password for ${DB_USER} [blank if none]: " DB_PASS
read -rp "Admin username [admin]: " ADMIN_USER
ADMIN_USER="${ADMIN_USER:-admin}"
read -rsp "New login credential for ${ADMIN_USER}: " ADMIN_CRED
echo

mysql_args=(-h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
if [ -n "$DB_PASS" ]; then
	mysql_args+=(-p"$DB_PASS")
fi

HASHED=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_CRED")

EXISTING=$(mysql -N "${mysql_args[@]}" "$DB_NAME" -e \
	"SELECT COUNT(*) FROM vtiger_users WHERE user_name = '${ADMIN_USER}';")

if [ "$EXISTING" -eq 0 ]; then
	echo "No user '${ADMIN_USER}' found in vtiger_users - nothing to reset."
	echo "This script only resets an existing admin's password; it does not create users."
	echo "Run vtiger's install wizard, or create the user first, then re-run this script."
	exit 1
fi

mysql "${mysql_args[@]}" "$DB_NAME" <<SQL
UPDATE vtiger_users
SET user_password = '${HASHED}', crypt_type = 'PHASH'
WHERE user_name = '${ADMIN_USER}';
SQL
echo "Password updated. You can now log in as ${ADMIN_USER} with the credential you just entered."
