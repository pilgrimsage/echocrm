# Production deployment

How to put this CRM on a server from a fresh `git clone`. Follow the steps in order. Commands assume a
Debian/Ubuntu server with Apache and the web user `www-data`; change names for your system.

> **`db/schema.sql` is for a new, empty database only.** It starts every table with `DROP TABLE IF EXISTS`
> (about 600 of them). Importing it into a database that holds real data deletes that data. `bin/setup.sh`
> asks for a database name: give it one that does not exist yet, or an empty one.

## 1. What the server needs

| | |
|---|---|
| PHP | 8.2 or newer (8.3 recommended; developed and tested on 8.4) |
| PHP extensions | `mysqli curl mbstring gd zip xml dom simplexml xmlreader xmlwriter openssl fileinfo intl iconv ctype zlib json bcmath soap`; `imap` only if you use Mail Manager or the Mail Converter (PHP 8.4 no longer ships it) |
| Database | MySQL 8.0 or newer (tested on 9.6), character set `utf8mb4`. MariaDB is untested |
| Web server | Apache 2.4 with `mod_alias`, `mod_authz_core`, `mod_headers`, and `AllowOverride All` for the install directory (the root `.htaccess` hides `db/`, `bin/`, config files, uploads and dot-files). On nginx you must add the same deny rules yourself, see section 7 |
| Tools | `git`, `composer` 2, the `mysql` client, `cron` |
| Network | outbound internet once during `composer install` (it builds the PDF fonts) |
| HTTPS | a certificate; do not run this over plain HTTP |

## 2. Get the code and the PHP libraries

    cd /var/www/html
    git clone https://github.com/pilgrimsage/sage-crm.git vtiger
    cd vtiger
    composer install --no-dev --optimize-autoloader

If the server has no `imap` extension and you do not need it, add `--ignore-platform-req=ext-imap`.
If the PDF fonts step failed (no network), run it again later with `composer run-script tc-lib-pdf-fonts`;
invoices and purchase orders cannot be printed until it has run.

## 3. Create the database and its own user

Do not let the application use `root`.

    mysql -u root -p <<'SQL'
    CREATE DATABASE vtigercrm CHARACTER SET utf8mb4;
    CREATE USER 'vtiger'@'localhost' IDENTIFIED BY 'a-long-random-password';
    GRANT ALL PRIVILEGES ON vtigercrm.* TO 'vtiger'@'localhost';
    FLUSH PRIVILEGES;
    SQL

## 4. Run the setup script

    bin/setup.sh

It asks for the environment (answer `production`), the database host, name, user and password, the site URL
(for example `https://crm.example.com/`) and the install directory (`/var/www/html/vtiger/`). It then:

- writes `config.inc.php` and `config.csrf-secret.php` with fresh random keys (both are git-ignored, they hold secrets),
- creates the runtime folders `cache/ logs/ storage/ test/templates_c/`,
- turns PHP error display off (production),
- imports `db/schema.sql` (settings, roles including **Accountant**, picklists, workflows, dashboard widgets, the chart of accounts; no users, no records),
- creates the administrator login (asks for username, email, time zone, a password of 8+ characters),
- clears the template cache.

Check that `config.inc.php` has no active line ending in `// STRICT DEVELOPMENT`. If it does, delete that line:
it shows PHP errors to every visitor.

## 5. File permissions

The web server user must be able to write to these, and nothing else:

    cd /var/www/html/vtiger
    chown -R www-data:www-data cache logs storage test user_privileges tabdata.php parent_tabdata.php
    chmod -R u+rwX,go-w cache logs storage test user_privileges
    # everything else stays owned by the deploy user, read-only for the web server
    chmod 640 config.inc.php config.csrf-secret.php

If you use Settings > Configuration Editor it writes `config.inc.php`, so that one file then has to be writable by
the web user (`chown www-data config.inc.php`). Otherwise leave it read-only.

## 6. Web server

Apache virtual host (the path and names are examples):

    <VirtualHost *:443>
        ServerName crm.example.com
        DocumentRoot /var/www/html/vtiger
        <Directory /var/www/html/vtiger>
            AllowOverride All
            Require all granted
        </Directory>
        SSLEngine on
        SSLCertificateFile    /etc/letsencrypt/live/crm.example.com/fullchain.pem
        SSLCertificateKeyFile /etc/letsencrypt/live/crm.example.com/privkey.pem
    </VirtualHost>
    <VirtualHost *:80>
        ServerName crm.example.com
        Redirect permanent / https://crm.example.com/
    </VirtualHost>

PHP settings (`/etc/php/8.x/apache2/php.ini`, or a file in `conf.d/`):

    display_errors = Off
    log_errors = On
    error_log = /var/www/html/vtiger/logs/php-error.log
    memory_limit = 512M          ; raise to 1G if you print or export very large reports
    max_execution_time = 300
    max_input_vars = 5000        ; invoices with many lines post a lot of fields
    upload_max_filesize = 32M
    post_max_size = 40M
    date.timezone = Asia/Kolkata ; your time zone
    opcache.enable = 1
    session.cookie_secure = 1
    session.cookie_httponly = 1

Restart Apache after changing them.

## 7. nginx (only if you do not use Apache)

`.htaccess` is ignored by nginx. Add at least:

    location ~ ^/(db|bin|migrate|schema|user_privileges|storage|logs?|backups?|cache|test/templates_c|includes/runtime/cache)(/|$) { return 404; }
    location ~ /\.(git|claude|github|idea|vscode) { return 404; }
    location ~* \.(sql|log|md|sample|lock|sh|ini|inc|tpl|bak|old)$ { return 404; }
    location ~* ^/(config[^/]*|composer\.(json|lock)|vtigerversion|PEAR|phpversionfail)\.php$ { return 404; }
    location ~* ^/(test|storage)/.*\.(php[0-9]?|phtml|phar)$ { return 404; }

then pass the remaining `.php` requests to PHP-FPM as usual.

## 8. Scheduled jobs (cron)

Workflows, recurring invoices, payment reminders, the mail scanner and scheduled imports all run from one cron
entry. As the web user (`sudo -u www-data crontab -e`):

    */15 * * * * cd /var/www/html/vtiger && /usr/bin/php -f vtigercron.php >> logs/cron.log 2>&1

Without it nothing scheduled runs. Settings > Automation > Scheduler lists the jobs and their last run.

## 9. Check the install before using it

Open the site over HTTPS and sign in as the administrator, then confirm:

    # each of these must answer 404 or 403, never 200
    curl -s -o /dev/null -w '%{http_code}  /db/schema.sql\n'        https://crm.example.com/db/schema.sql
    curl -s -o /dev/null -w '%{http_code}  /bin/setup.sh\n'         https://crm.example.com/bin/setup.sh
    curl -s -o /dev/null -w '%{http_code}  /config.inc.php\n'       https://crm.example.com/config.inc.php
    curl -s -o /dev/null -w '%{http_code}  /.git/config\n'          https://crm.example.com/.git/config
    curl -s -o /dev/null -w '%{http_code}  /user_privileges/\n'     https://crm.example.com/user_privileges/
    curl -s -o /dev/null -w '%{http_code}  /storage/\n'             https://crm.example.com/storage/

- The Home page loads without any PHP message on screen.
- Ledgers > **Books Health** (in the Ledgers toolbar) shows five green zeros.
- The footer shows the right year and the page loads with the CSS (a bare page means the template cache or `AllowOverride` is wrong).

## 10. First-time setup inside the application

Do these in order; the accounting parts assume you keep the books here.

1. **Settings > Company Details**: name, address, logo and the **GSTIN / VAT ID** (it is validated).
2. **Settings > Outgoing Server** (SMTP): needed for emailing invoices and for payment reminders. Send yourself a test mail.
3. **Settings > Currencies**: the base currency is INR; add others only if you invoice in them (each needs a rate).
4. **Settings > Inventory > Tax Calculations**: check the CGST, SGST and IGST rates and the tax system (India GST).
5. **Settings > Users**: create the people. Give accountants the **Accountant** role (every accounting screen except Accounting Settings and Financial Year Close). The accounting modules are invisible to every other role unless you grant them in the profile.
6. **Ledgers > Accounting Settings** (administrator): the month the financial year starts (April by default), the books start date, the ledgers each automatic posting uses, and, if your business is not a trading business, apply the matching chart-of-accounts template. Leave the lock date empty until a period is final.
7. **Bank Accounts**: add each bank account and the cash account with their opening balance and opening date, then **Ledgers** opening balances if you have them. Do this before any transaction is entered.
8. **Products and Services**: give each an HSN/SAC code and a GST rate (invoices without one are flagged in the GST returns' data checks); tick *track stock* only where you count stock.
9. **Invoices > Payment Reminders**: review the four starting rules; tick "send automatically every day" only after step 2 works.
10. **Settings > Automation > Workflows**: review the four accounting workflows (they create to-dos), edit thresholds or switch them off.
11. **Ledgers > Withholding Sections**: check the TDS rates against the current rules; they are examples.
12. Enter or import your customers, vendors and products, then start working. Imports of accounting records (bank transactions, payments, invoices) are checked by the same rules and posted to the journal as they load; use plain numbers in the file (`1250.50`, not `1,250.50`).

Practise the invoice, payment and report flow on a **staging copy** (a second database made with `bin/setup.sh`) before real use: posted accounting data cannot simply be deleted afterwards, only reversed.

## 11. Backups

Take both, daily, and keep copies off the server:

    mysqldump --single-transaction --routines -u vtiger -p vtigercrm | gzip > /backups/vtigercrm-$(date +%F).sql.gz
    tar czf /backups/vtiger-files-$(date +%F).tgz -C /var/www/html/vtiger storage test/logo test/user test/upload test/product test/contact config.inc.php config.csrf-secret.php

Test a restore on another server now and then (`gunzip < dump.sql.gz | mysql ...`). The application's own copy of
`db/schema.sql` is not a backup.

## 12. Updating later

    cd /var/www/html/vtiger
    git pull
    composer install --no-dev --optimize-autoloader
    rm -rf test/templates_c/v7/*          # always after a pull that touches .tpl files
    sudo -u www-data php -r 'opcache_reset();' 2>/dev/null; sudo systemctl reload apache2

Then hard-refresh the browser. Never re-run `bin/setup.sh` or import `db/schema.sql` on the live database. A change
that needs a database change will say so in its commit message or release note; apply it by hand on a backup first.

## 13. What is in `bin/`

| File | Purpose |
|---|---|
| `bin/setup.sh` | the one-shot install of section 4 |
| `bin/create-admin.php` | creates an administrator, or resets an administrator's password: `ADMIN_PASSWORD='...' php bin/create-admin.php --user=admin --email=you@example.com` |
| `bin/dump-db.sh` | developers only: rebuilds `db/schema.sql` from the development database after DB-level changes (users and records are stripped) |
| `bin/lib/scrub-installer-db.php` | used by `dump-db.sh` |

The one-time scripts that built the accounting modules and converted older data (`create-*`, `fix-*`, `convert-*`,
`remove-*`, `restrict-*`, `add-account-gstin`, `lib/module-setup.php`) were removed on 2026-10-18: `db/schema.sql`
already holds their result, so a new install does not need them. They are in git history (before commit `bin: remove
one-time scripts`) if you ever need to see how a module was set up.

## 14. If something is wrong

| Symptom | Check |
|---|---|
| White page / 500 | `logs/php-error.log`, the Apache error log; permissions of section 5; PHP version and extensions |
| Page has no styling or old layout after an update | `rm -rf test/templates_c/v7/*`, then hard-refresh |
| Invoice/PO PDF will not generate | `composer run-script tc-lib-pdf-fonts` (needs internet), `memory_limit` for big documents |
| "Not enough stock" on an invoice | the product has *track stock* on and its quantity is below the line; receive the purchase order first |
| Scheduled things never run | the cron entry of section 8 and `logs/cron.log` |
| Emails are not sent | Settings > Outgoing Server and the reminder log (Invoices > Payment Reminders) |
