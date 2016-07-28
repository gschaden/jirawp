Jira worklog proxy
==================

This script exposes a calendar server, all entries are worklog entries for your jira issues

# Quick setup
* copy settings.php.sample to settings.php
    * change JIRA_URL to point to your jira instance
    * change BASE_URI to reflect the location of this scripts
* create a sqlite3 db in data/db.sqlite
    * [Install db](http://sabre.io/dav/caldav/)
    * apply patch from sql directory
* to use with apache
    * copy htaccess to .htaccess
# Schema changes:

> ALTER TABLE calendarobjects add column jiraid text;
