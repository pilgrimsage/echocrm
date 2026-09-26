#!/usr/bin/env bash
# Sets a working login for the admin account after importing db/schema.sql,
# whose admin row ships with a placeholder credential (see bin/dump-db.sh).
# Uses PHP's own password_hash(), matching modules/Users/Users.php's PHASH
# path, so vtiger accepts it on the next login.
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
	# db/schema.sql intentionally ships vtiger_users empty (see bin/dump-db.sh),
	# while every other table's data - including role/permission links keyed
	# to user id 1 - is imported as-is. Recreate that same user id so those
	# links resolve correctly.
	mysql "${mysql_args[@]}" "$DB_NAME" <<SQL
INSERT INTO vtiger_users (id, user_name, user_password, is_admin, status, crypt_type, first_name, last_name, email1)
VALUES (1, '${ADMIN_USER}', '${HASHED}', 'on', 'Active', 'PHASH', 'Admin', 'User', '${ADMIN_USER}@example.com');
SQL
	echo "Created ${ADMIN_USER} (id=1). You can now log in with the credential you just entered."
else
	mysql "${mysql_args[@]}" "$DB_NAME" <<SQL
UPDATE vtiger_users
SET user_password = '${HASHED}', crypt_type = 'PHASH', status = 'Active'
WHERE user_name = '${ADMIN_USER}';
SQL
	echo "Updated. You can now log in as ${ADMIN_USER} with the credential you just entered."
fi
