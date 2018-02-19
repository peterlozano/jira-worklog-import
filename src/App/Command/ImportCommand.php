<?php

namespace App\Command;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Worklog;
use League\Csv\Reader;
use JiraRestApi\JiraException;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

const DEFAULT_DATE_FORMAT = 'd/m/y H:i:s';
const DEFAULT_DATE_TIMEZONE = 'Europe/Madrid';

class ImportCommand extends Command
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:import')
            // the short description shown while running "php bin/console list"
            ->setDescription('Import data.')
            ->addArgument('filename', InputArgument::REQUIRED, 'The filename to import from.')
            ->addOption('real-import', 'ri', InputOption::VALUE_NONE, 'Disable the test mode and do a real import.')
            ->addOption('csv-date-format', 'df', InputOption::VALUE_REQUIRED, 'Specify a custom date format in the csv.', DEFAULT_DATE_FORMAT)
            ->addOption('csv-timezone', 'tz', InputOption::VALUE_REQUIRED, 'Specify a custom timezone in the csv.', DEFAULT_DATE_TIMEZONE)
            ->addOption('csv-delimiter', 'dl', InputOption::VALUE_REQUIRED, 'Specify the csv delimiter.', ',')
            ->addOption('offset', 'of', InputOption::VALUE_REQUIRED, 'Number of rows in the csv to skip', 1)
            ->addOption('limit', 'li', InputOption::VALUE_REQUIRED, 'Number of rows in the csv to import', 1000)
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Debug mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $debug = $input->getOption('debug');

        $csv = Reader::createFromPath($input->getArgument('filename'));

        $input_bom = $csv->getInputBOM();
        if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
            $output->writeln('Converting CSV from UTF-16 to UTF-8\n');
            $csv->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
        }


        $res = $csv
            ->setDelimiter($input->getOption('csv-delimiter'))
            ->setOffset($input->getOption('offset'))
            ->setLimit($input->getOption('limit'))
            ->fetchAll();

        foreach ($res as $line) {
            if ($debug) {
                print_r($line);
            }

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
                sscanf($line[11], "%d:%d:%d", $hours, $minutes, $seconds);
                // Round hours to the nearest 15 minutes.
                // FIXME: Make this rounding configurable.
                $hours = ceil(($hours + $minutes / 60 + $seconds / 3600) / 0.25) * 0.25;

                if ($debug) {
                    echo "DATE: $date_value TIME: $time_value ISSUE: $issueKey COMMENT: $comment SPENT: $hours\n";
                }

                // Make sure timezone is correct, it can have an impact on the day the timelog is saved into.
                $date = \DateTime::createFromFormat(
                    $input->getOption('csv-date-format'),
                    $date_value . ' ' . $time_value,
                    new \DateTimeZone($input->getOption('csv-timezone'))
                );

                echo implode(', ', array($date->format('Y-m-d H:i:s'), $issueKey, $comment, $hours)) . "h\n";

                try {
                    $workLog = new Worklog();
                    $workLog->setComment($comment)
                        ->setStarted($date)
                        ->setTimeSpent($hours . 'h');

                    $issueService = new IssueService();

                    if ($input->getOption('real-import')) {
                        $ret = $issueService->addWorklog($issueKey, $workLog);
                        $workLogid = $ret->{'id'};

                        // Show output from the api call
                        if ($debug) {
                            var_dump($ret);
                        }
                    }
                    else {
                        if ($debug) {
                            print_r($issueKey);
                            print_r($workLog);
                        }
                    }
                } catch (JiraException $e) {
                    echo 'ERROR: ' .$e->getMessage() . "\n";
                }
            }
        }
    }
}