<?php
/* Settings */
define("BASE_URI", $_ENV['BASE_URI'] ?: "/");
define("JIRA_URL", $_ENV['JIRA_URL'] ?: "http://my.jira.com/jira");

date_default_timezone_set('Europe/Vienna');

/* Database */
$pdo = new PDO('sqlite:data/db.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
