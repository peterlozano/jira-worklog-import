<?php

/**
 * Script to import a csv file with timelogs to Jira.
 *
 * Jira credentials must be on an .env file with this format:
 * JIRA_HOST="https://<SUBDOMAIN>.atlassian.net"
 * JIRA_USER=""
 * JIRA_PASS=""
 *
 * TODO: Automatically convert H:M:S worklog spent time into decimal.
 * TODO: Automatically fetch time from Toggl.
 * TODO: Use console library to have some console help and parameters. (ie: debug, testing, source file)
 */

require __DIR__ . '/vendor/autoload.php';

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Worklog;
use JiraRestApi\JiraException;

use League\Csv\Reader;

const DATE_FORMAT = 'd/m/y H:i:s';
const DATE_TIMEZONE = 'Europe/Madrid';

// Change this to false to do a real import. Make sure column numbers are ok first.
const TESTING = true;

$csv = Reader::createFromPath('timelogs.csv');

/**
 * Use offset to skip the header row.
 * Use limit = 1 to test with just 1 row.
 */
$res = $csv->setOffset(1)->setLimit(1000)->fetchAll();

foreach ($res as $line) {
    // Just debug print
    print_r($line);

    if (!empty($line[1])) {
        // FIXME: Make this configurable somehow or autodetected from csv header.
        $date_value = $line[7];
        // Time is hardcoded to always the same value.
        $time_value = "12:00:00";

        // Issue key.
        $issueKey = $line[12];

        // The description of the task.
        $comment  = $line[5];

        // Time spent, in decimal value.
        // TODO: Automatically convert from a H:M:S format to decimal
        $hours = $line[13];

        // Just debug
        echo "DATE: $date_value TIME: $time_value ISSUE: $issueKey COMMENT: $comment SPENT: $hours\n";

        // Make sure timezone is correct, it can have an impact on the day the timelog is saved into.
        $date = DateTime::createFromFormat(DATE_FORMAT, $date_value . ' ' . $time_value, new DateTimeZone(DATE_TIMEZONE));

        echo implode(', ', array($date->format('Y-m-d H:i:s'), $issueKey, $comment, $hours)) . "h\n";

        try {
            $workLog = new Worklog();
            $workLog->setComment($comment)
                ->setStarted($date)
                ->setTimeSpent($hours . 'h');

            $issueService = new IssueService();

            // Use $testing to test the csv reading without sending to jira
            if (!TESTING) {
                $ret = $issueService->addWorklog($issueKey, $workLog);
                $workLogid = $ret->{'id'};

                // Show output from the api call
                var_dump($ret);
            }
            else {
                print_r($issueKey);
                print_r($workLog);
            }
        } catch (JiraException $e) {
            echo 'ERROR: ' .$e->getMessage() . "\n";
        }
    }
}
