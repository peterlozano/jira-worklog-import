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

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Command\ImportCommand());

$application->run();
