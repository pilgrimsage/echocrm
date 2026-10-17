# Vtiger CRM

Vtiger is a PHP based web application that enables businesses to increase sales wins, marketing ROI, and support satisfaction by providing tools for employees and management work more effectively, capture more data, and derive new actionable insights from across the customer lifecycle.

## Local setup

`config.inc.php`, `config.csrf-secret.php`, and `kcfinder/config.php` are gitignored since they hold DB credentials and secrets that differ per machine. Everything else needed to run is in the repo.

On a fresh clone:

    composer install --ignore-platform-req=ext-imap
    bin/setup.sh

`bin/setup.sh` prompts for the DB host/name/credentials and the site URL, writes `config.inc.php` and `config.csrf-secret.php` (with a fresh `application_unique_key` and CSRF secret), creates the database, imports `db/schema.sql`, and asks for the administrator's username, email, time zone and password, which it creates with `bin/create-admin.php`.

`db/schema.sql` is installer data: the full schema plus the settings a new install needs (roles and profiles including the Accountant role, picklists, fields and filters, workflows, dashboard widgets, the chart of accounts). It contains **no users and no records** (no customers, documents, payments or journal entries).

To set a new password for an existing administrator, or to create one later:

    ADMIN_PASSWORD='...' php bin/create-admin.php --user=admin --email=you@example.com

To regenerate `db/schema.sql` after DB-level changes (Module Designer edits, workflows, picklists, one of the `bin/create-*` scripts) so future installs pick them up:

    DB_PASS='...' bin/dump-db.sh

It copies the development database to a scratch database named `<name>_installer`, removes users and records there (`bin/lib/scrub-installer-db.php`), dumps it and drops the copy. The development database is only read.

If you'd rather set things up by hand, copy `config.inc.php.sample` to `config.inc.php`, `config.csrf-secret.php.sample` to `config.csrf-secret.php`, and `kcfinder/config.php.sample` to `kcfinder/config.php`, edit DB credentials, `site_URL`, `root_directory` and `application_unique_key`, import `db/schema.sql`, then run `bin/create-admin.php`.

## Get involved

Development on vtiger is done at https://code.vtiger.com

**Note**: Any contributions submitted to Vtiger project should be made available under Vtiger Public License. 
If contribution has any patented code, or commercial code, then please communicate with Vtiger team before making the contribution.

https://www.vtiger.com/vtiger-public-license/

To register for an account, please contact community @ vtiger.com, you will need this to file issues and/or fix the code
Once you have an account, you can [browse the code](https://code.vtiger.com/vtiger/vtigercrm/tree/master),
[see if your issue is already reported](https://code.vtiger.com/vtiger/vtigercrm/issues) and if you have a new problem
to report you can [create an issue](https://code.vtiger.com/vtiger/vtigercrm/issues/new?issue)

If you then want to fix the issue (or another issue) you can create your own fork of vtiger to work on using the
fork button on the vtiger project, this will create a new git repository for you at
    
    https://code.vtiger.com/yourname/vtigercrm.git

on your computer you will need a git client installed and you need to tell git who you are:

    git config --global user.name "YOUR NAME"
    git config --global user.email "YOUR EMAIL ADDRESS"

now clone your fork of vtiger

    git clone https://code.vtiger.com/yourname/vtigercrm.git

this will pull down from the server your copy of the vtiger code and all the history.

You will make a new branch for your changes, you can give it a descriptive name, once the branch is created
you will switch to that branch using the checkout command

    git branch fix_projects_on_calendar
    git checkout fix_projects_on_calendar

Before you install, you need to run ```composer update```

Now you can make your changes and commit all changed files with

    git commit -a

Do reference the issue number in your commit message, e.g. "fix #2 display projects on the calendar" the number will
allow the system to link the commit to the issue.

Now you can push your branch to the server, this creates the branch on the server end and populates it

    git push --set-upstream origin fix_projects_on_calendar

look at the branch on code.vtiger.com and create a merge request from your branch
to the upstream master, this will be reviewed to see if it fixes the 
issue and if all is good will be merged into the upstream code.
You can then switch back to your master branch with

    git checkout master

And you can create additional feature branches from there to fix different things.

If there have been other changes to the central vtiger code that you want in your work area then you can add the central
repository as an upstream remote (only need to do this bit once), then you can fetch changes and merge them

    git remote add upstream https://code.vtiger.com/vtiger/vtigercrm.git
    git fetch upstream
    git merge upstream/master
