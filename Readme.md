Jira Worklog Proxy
==================

This app gives you a calendar server, all entries represent worklog entries for
your jira issues. Authentication is done through the jira api, there is no need
to create local users.

# Setup

## Assemble Project

```
make assemble
```

## Environment

```
export JIRA_URL=https://my.jira.com/jira
```

## Run Server

```
make run
```

# Usage

## Long Running Issues

For long running issues:

* Create a calendar with description set to the jira issue key (e.g: **PROJ-1234**)
* Create events, start time, duration and summary (description) will be set for the worklog entry

## Small Tasks

For smaller tasks:

* Create calender with description set to "OTHER"
* Create event, give them a name **jira issue key** **description**  (e.g: PROJ-345 update documentation)

# OS X Calendar

Create CalDAV account

* Account Type: Advanced
* User Name: your jira login (e.g. ges)
* Password: your jira password
* Server Address: hostname or ip of server running this application (192.168.0.1)
* Server Path: /**BASE_URI**/principals/**User Name** (e.g: /jirawp/principals/ges )
* Port: 80
* SSL: no

# CalDAV-Sync

Create CalDAV account

* Server: hostname/**BASE_URI**/principals/**User Name** (e.g: 192.168.0.1/jirawp/principals/ges )
* Username: your jira user name
* Password: your jira password

# Jira Compatibility

* 6.4
* 7.X

# Client Compatibilty

* OS X 10.10, ical 8.0
* Android, CalDAV-Sync 0.4.27
    
# FAQ

## I get an error in my client

Check the error log of the HTTP server

## I get a forbidden exception

Check if the issue you are trying to update is editable. You cannot log work on closed issues.
    
# Schema changes

> ALTER TABLE calendarobjects add column jiraid text;
