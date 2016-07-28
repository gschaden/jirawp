Jira worklog proxy
==================

This script exposes a calendar server, all entries are worklog entries for your jira issues.
Authenication is done through the jira api, there is no need to create local users.

# Quick setup
* use composer to install dependencies
    composer install
* copy settings.php.sample to settings.php
    * change JIRA_URL to point to your jira instance
    * change BASE_URI to reflect the location of this scripts
* create a sqlite3 db in data/db.sqlite
    * [Install db](http://sabre.io/dav/caldav/)
    mkdir data
    cat vendor/sabre/dav/examples/sql/sqlite.* | sqlite3 data/db.sqlite
    * apply patch from sql directory
    cat sql/sqlite.* | sqlite3 data/db.sqlite
    * make sure the webserver can write the database
    chmod a+rw data/db.sqlite
* to use with apache
    * copy htaccess to .htaccess
    
# Usage
* For long running issues, create a calendar with description set to the jira issue key (e.g PROJ-1234)
    * create events, start time, duration and summary (description) will be set for the worklog entry
* For smaller tasks 
    * create calender with description set to "OTHER"
    * create events, give them a name <jira issue key> <description>  (e.g PROJ-345 update documentation)

# OS X ical
* Create a caldav account
     * Account Type: Advanced 
     * User Name: your jira login (e.g. ges)
     * Password: your jira password 
     * Server Address: hostname or ip of server running this application (192.168.0.1)
     * Server Path: /<BASE_URI>/principals/<User Name> (e.g: /jirawp/principals/ges )
     * Port: leave blank     

# Jira compatibility
* 6.4
* 7.X

# Client compatibilty
* OS X 10.10, ical 8.0
    
# FAQ
## I get an error in my client
Check the error log of the http server

## I get a forbidden exception
Check if the issue you are trying to update is editable. You cannot log work on closed issues. 
    
# Schema changes:
> ALTER TABLE calendarobjects add column jiraid text;
