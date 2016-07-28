Jira worklog proxy
==================

This script exposes a calendar server, all entries are worklog entries for your jira issues.
Authenication is done through the jira api, there is no need to create local users.

# Quick setup
* use composer to install dependencies
> composer install
* copy settings.php.sample to settings.php
    * change JIRA_URL to point to your jira instance
    * change BASE_URI to reflect the location of this scripts
* create a sqlite3 db in data/db.sqlite
    * [Install db](http://sabre.io/dav/caldav/)
    * apply patch from sql directory
* to use with apache
    * copy htaccess to .htaccess

# OS X ical
* Create a caldav account
     * Account Type: Advanced 
     * User Name: your jira login (e.g. ges)
     * Password: your jira password 
     * Server Address: hostname or ip of server running this application (192.168.0.1)
     * Server Path: /<BASE_URI>/principals/<User Name> (e.g: /jirawp/principals/ges )
     * Port: leave blank     

# Jira compatibility
* 7.X

# Client compatibilty
* OS X 10.10, ical 8.0
    
    
    
# Schema changes:

> ALTER TABLE calendarobjects add column jiraid text;
