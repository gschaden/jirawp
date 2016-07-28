<?php

use Sabre\VObject;

// install error handler
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (E_RECOVERABLE_ERROR === $errno) {
        throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
    }
    return false;
}
set_error_handler('myErrorHandler');

include_once "settings.php";

require __DIR__ . "/vendor/autoload.php";
$principalBackend = new Sabre\DAVACL\PrincipalBackend\PDO($pdo);
$jiraClient = null;

/**
 * Class JiraConnector
 * Service to with all the jira stuff
 */
class JiraConnector
{
    protected $api;

    public function __construct($username, $password, $baseUrl)
    {
        $this->api = new \chobie\Jira\Api(
            $baseUrl,
            new \chobie\Jira\Api\Authentication\Basic($username, $password)
        );
    }

    public function createWorklog($issueKey, $started, $timeSpentSeconds, $comment)
    {
        //array("comment" => $comment, "started" => "2016-07-11T04:12:44.994+0000", "timeSpentSeconds" => 12000));
        return $this->api->api(\chobie\Jira\Api::REQUEST_POST, sprintf('/rest/api/2/issue/%s/worklog', $issueKey),
            array("comment" => $comment, "started" => $started, "timeSpentSeconds" => $timeSpentSeconds));
    }


    public function updateWorklog($issueKey, $id, $started, $timeSpentSeconds, $comment)
    {
        //array("comment" => $comment, "started" => "2016-07-11T04:12:44.994+0000", "timeSpentSeconds" => 12000));
        return $this->api->api(\chobie\Jira\Api::REQUEST_PUT, sprintf('/rest/api/2/issue/%s/worklog/%s', $issueKey, $id),
            array("comment" => $comment, "started" => $started, "timeSpentSeconds" => $timeSpentSeconds));
    }

    public function deleteWorklog($issueKey, $id)
    {
        //array("comment" => $comment, "started" => "2016-07-11T04:12:44.994+0000", "timeSpentSeconds" => 12000));
        return $this->api->api(\chobie\Jira\Api::REQUEST_DELETE, sprintf('/rest/api/2/issue/%s/worklog/%s', $issueKey, $id),
            array());
    }

    /**
     * @return array|\chobie\Jira\Api\Result|false
     * any function that checks the credentials
     */
    public function validateCredentials()
    {
        return $this->api->api(\chobie\Jira\Api::REQUEST_GET, '/rest/api/2/serverInfo', array());
    }

}

/* Backends */
$authBackend = new Sabre\DAV\Auth\Backend\BasicCallBack(

    function ($username, $password) {
        global $principalBackend;
        global $jiraClient;

//        error_log("authcallback $username, $password");

        try {
            $jiraClient = new JiraConnector($username, $password, JIRA_URL);

            $result = $jiraClient->validateCredentials(); // on error an exception is thrown

            // autocreate principal
            $p = $principalBackend->getPrincipalByPath("principals/$username");
            if (!$p) {
                $mkcol = new Sabre\DAV\MkCol([], [
                ]);
                $principalBackend->createPrincipal("principals/$username", $mkcol);
            }
        } catch (Exception $e) {
            error_log($e);
            return false;
        }
        return $result;
    }
);


class MyPDO extends Sabre\CalDAV\Backend\PDO
{
    const ISSUE_REGEX = '/^([A-Za-z]+-[0-9]+)/';
    const JIRA_DATE_FORMAT = 'Y-m-d\TH:i:s.000O';


    /**
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return null|string
     * intercept create calender to add the jira id
     */
    function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        global $jiraClient;
        error_log("createCalendarObject: ". implode(",", $calendarId) . " $objectUri $calendarData");

        $jiraKey = $this->getIssueKeyForCalendar($calendarId, $calendarData);
        if (!$jiraKey)
            throw new \Sabre\DAV\Exception\Forbidden();

        // calculate dates, get summary
        $extra = $this->getExtraData($calendarData);

        // update jira issue
        $jiraWorklogEntry = $jiraClient->createWorklog($jiraKey, $extra["startDate"]->format(self::JIRA_DATE_FORMAT),
            $extra["duration"],
            $this->getComment($extra["summary"]));
        error_log("createWorklog: " . print_r($jiraWorklogEntry, true));
        if (isset($jiraWorklogEntry->getResult()["errorMessages"]))
            throw new \Sabre\DAV\Exception\Forbidden();

        $result = parent::createCalendarObject($calendarId, $objectUri, $calendarData);
        if (!$result) return $result;

        // update jira issue id
        list($calendarId, $instanceId) = $calendarId;
        $stmt = $this->pdo->prepare('UPDATE ' . $this->calendarObjectTableName . ' SET jiraid = ? WHERE calendarid = ? AND uri = ?');
        $stmt->execute([$jiraWorklogEntry->getResult()["id"], $calendarId, $objectUri]);

        return $result;
    }
    function deleteCalendarObject($calendarId, $objectUri)
    {
        global $jiraClient;
        error_log("deleteCalendarObject: ". implode(",", $calendarId) . " $objectUri");

        $result = $this->getCalendarObject($calendarId, $objectUri);

        $jiraKey = $this->getIssueKeyForCalendar($calendarId, $result["calendardata"]);
        error_log("jiraKey: $jiraKey");

        $jiraWorklogId = $this->getWorklogId($calendarId, $objectUri);
        error_log("jiraWorklogId: $jiraWorklogId");

        parent::deleteCalendarObject($calendarId, $objectUri);

        if ($jiraWorklogId) {
            // update jira issue
            $jiraWorklogEntry = $jiraClient->deleteWorklog($jiraKey, $jiraWorklogId);
            error_log("deleteWorklog: " . print_r($jiraWorklogEntry, true));
        }
    }

    function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        global $jiraClient;
        error_log("updateCalendarObject: ". implode(",", $calendarId) . " $objectUri $calendarData");

        $result = parent::updateCalendarObject($calendarId, $objectUri, $calendarData);
        if (!$result) return $result;
        error_log("result: $result");

        $jiraKey = $this->getIssueKeyForCalendar($calendarId, $calendarData);
        if (!$jiraKey) return $result;
        error_log("jiraKey: $jiraKey");

        $jiraWorklogId = $this->getWorklogId($calendarId, $objectUri);
        if (!$jiraWorklogId) return $result;
        error_log("jiraWorklogId: $jiraWorklogId");

        // calculate dates, get summary
        $extra = $this->getExtraData($calendarData);

        // update jira issue
        $jiraWorklogEntry = $jiraClient->updateWorklog($jiraKey, $jiraWorklogId,
            $extra["startDate"]->format(self::JIRA_DATE_FORMAT), $extra["duration"],
            $this->getComment($extra["summary"]));
        error_log("updateWorklog: " . print_r($jiraWorklogEntry, true));

        return $result;
    }

    /**
     * @param $summary
     * @return string
     * extract comment from summary, might be an issue key at the beginning
     */
    function getComment($summary)
    {
        if ($summary == "New Event" || $summary == "Neues Ereigniss")
            return "";
        if (preg_match(self::ISSUE_REGEX, $summary, $matches)) {
            return substr($summary, strlen($matches[1]) + 1);
        }
        return $summary;
    }

    /**
     * @param $calendarId
     * @param $objectUri
     * @return null
     * get worklogid from the database
     */
    function getWorklogId($calendarId, $objectUri)
    {
        // update jira issue id
        list($calendarId, $instanceId) = $calendarId;
        $stmt = $this->pdo->prepare('SELECT jiraid FROM ' . $this->calendarObjectTableName . ' WHERE calendarid = ? AND uri = ?');
        $stmt->execute([$calendarId, $objectUri]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) return null;

        return $row['jiraid'];
    }

    /**
     * @param $calendarId
     * @param $calendarData
     * @return mixed|null
     * find the issuekey for this calendar entry
     */
    function getIssueKeyForCalendar($calendarId, $calendarData)
    {
        $calendarInstance = $this->getCalendarInstance($calendarId);
        error_log("calendarinstance: " . print_r($calendarInstance, true));
        if (!$calendarInstance) return null;

        // find issue id
        // if calendardescription == OTHER use jirakey from summary
        // if calendardescription is a jiraky use it
        // if displayname is a jirakey use it
        if (trim($calendarInstance["description"]) == "OTHER" ||
            trim($calendarInstance["displayname"]) == "OTHER"
        ) {
            $extra = $this->getExtraData($calendarData);
            if (preg_match(self::ISSUE_REGEX, $extra["summary"], $matches)) {
                return $matches[1];
            }
        }
        if (preg_match(self::ISSUE_REGEX, $calendarInstance["description"])) {
            return $calendarInstance["description"];
        } elseif (preg_match(self::ISSUE_REGEX, $calendarInstance["displayname"])) {
            return $calendarInstance["displayname"];
        }
        error_log("no jira key found in displayname and description");
        return null;
    }

    function getExtraData($calendarData)
    {
        // calculate dates
        $extraData = $this->getDenormalizedData($calendarData);
        $dtStart = $extraData["firstOccurence"];
        $dtEnd = $extraData["lastOccurence"];
        $dtDuration = $dtEnd - $dtStart;

        $d = new DateTime();
        $d->setTimestamp($dtStart);
        return [
            "startDate" => $d,
            "duration" => $dtDuration,
            "summary" => $extraData["summary"],
        ];
    }



    function getCalendarInstance($calendarId)
    {

        if (!is_array($calendarId)) {
            throw new \InvalidArgumentException('The value passed to $calendarId is expected to be an array with a calendarId and an instanceId');
        }
        list($calendarId, $instanceId) = $calendarId;

        $stmt = $this->pdo->prepare('SELECT id, calendarid, displayname, description FROM ' . $this->calendarInstancesTableName . ' WHERE calendarid = ? AND id = ?');
        $stmt->execute([$calendarId, $instanceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) return null;

        return [
            'id' => $row['id'],
            'calendarid' => $row['calendarid'],
            'displayname' => $row['displayname'],
            'description' => $row['description'],
        ];
    }


    /**
     * @param string $calendarData
     * @return array
     * @throws \Sabre\DAV\Exception\BadRequest
     * like base method, but returns the summary
     */
    protected function getDenormalizedData($calendarData)
    {

        $vObject = VObject\Reader::read($calendarData);
        $componentType = null;
        $component = null;
        $firstOccurence = null;
        $lastOccurence = null;
        $uid = null;
        $summary = null;
        foreach ($vObject->getComponents() as $component) {
            if ($component->name !== 'VTIMEZONE') {
                $componentType = $component->name;
                $uid = (string)$component->UID;
                break;
            }
        }

        if (!$componentType) {
            throw new \Sabre\DAV\Exception\BadRequest('Calendar objects must have a VJOURNAL, VEVENT or VTODO component');
        }
        if ($componentType === 'VEVENT') {
            $summary = isset($component->SUMMARY) ? $component->SUMMARY->getValue() : "no summary";

            $firstOccurence = $component->DTSTART->getDateTime()->getTimeStamp();
            // Finding the last occurence is a bit harder
            if (!isset($component->RRULE)) {
                if (isset($component->DTEND)) {
                    $lastOccurence = $component->DTEND->getDateTime()->getTimeStamp();
                } elseif (isset($component->DURATION)) {
                    $endDate = clone $component->DTSTART->getDateTime();
                    $endDate = $endDate->add(VObject\DateTimeParser::parse($component->DURATION->getValue()));
                    $lastOccurence = $endDate->getTimeStamp();
                } elseif (!$component->DTSTART->hasTime()) {
                    $endDate = clone $component->DTSTART->getDateTime();
                    $endDate = $endDate->modify('+1 day');
                    $lastOccurence = $endDate->getTimeStamp();
                } else {
                    $lastOccurence = $firstOccurence;
                }
            } else {
                $it = new VObject\Recur\EventIterator($vObject, (string)$component->UID);
                $maxDate = new \DateTime(self::MAX_DATE);
                if ($it->isInfinite()) {
                    $lastOccurence = $maxDate->getTimeStamp();
                } else {
                    $end = $it->getDtEnd();
                    while ($it->valid() && $end < $maxDate) {
                        $end = $it->getDtEnd();
                        $it->next();

                    }
                    $lastOccurence = $end->getTimeStamp();
                }

            }

            // Ensure Occurence values are positive
            if ($firstOccurence < 0) $firstOccurence = 0;
            if ($lastOccurence < 0) $lastOccurence = 0;
        }

        // Destroy circular references to PHP will GC the object.
        $vObject->destroy();

        return [
            'etag' => md5($calendarData),
            'size' => strlen($calendarData),
            'componentType' => $componentType,
            'firstOccurence' => $firstOccurence,
            'lastOccurence' => $lastOccurence,
            'uid' => $uid,
            'summary' => $summary,
        ];

    }


}

$calendarBackend = new MyPDO($pdo);

// Directory structure
$tree = [
    new Sabre\CalDAV\Principal\Collection($principalBackend),
    new Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend),
];

$server = new Sabre\DAV\Server($tree);

if (defined("BASE_URI"))
    $server->setBaseUri(BASE_URI);

/* Server Plugins */
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend);
$server->addPlugin($authPlugin);

$aclPlugin = new Sabre\DAVACL\Plugin();
$server->addPlugin($aclPlugin);

/* CalDAV support */
$caldavPlugin = new Sabre\CalDAV\Plugin();
$server->addPlugin($caldavPlugin);
//
///* Calendar subscription support */
//$server->addPlugin(
//    new Sabre\CalDAV\Subscriptions\Plugin()
//);
//
///* Calendar scheduling support */
//$server->addPlugin(
//    new Sabre\CalDAV\Schedule\Plugin()
//);

/* WebDAV-Sync plugin */
$server->addPlugin(new Sabre\DAV\Sync\Plugin());

///* CalDAV Sharing support */
//$server->addPlugin(new Sabre\DAV\Sharing\Plugin());
//$server->addPlugin(new Sabre\CalDAV\SharingPlugin());

// Support for html frontend
$browser = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($browser);

// And off we go!
$server->exec();
