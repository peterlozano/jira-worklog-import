<?php

namespace App\Command;

use App\Config\ImportConfig;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Worklog;
use League\Csv\Reader;
use JiraRestApi\JiraException;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    /**
     * @var \App\Config\ImportConfig
     */
    private $config;

    /**
     * Configures the current command.
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        // Loads import config from .env file.
        $this->config = new ImportConfig();

        $this
            ->setName('app:import')
            ->setDescription('Import data.')
            ->addArgument('filename', InputArgument::REQUIRED, 'The filename to import from.')
            ->addOption('real-import', 'ri', InputOption::VALUE_NONE, 'Disable the test mode and do a real import.')
            ->addOption('csv-date-format', 'df', InputOption::VALUE_REQUIRED, 'Specify a custom date format in the csv.', $this->config->getCsvDateFormat())
            ->addOption('csv-date-timezone', 'tz', InputOption::VALUE_REQUIRED, 'Specify a custom timezone in the csv.', $this->config->getCsvDateTimezone())
            ->addOption('csv-delimiter', 'dl', InputOption::VALUE_REQUIRED, 'Specify the csv delimiter.', $this->config->getCsvDelimiter())
            ->addOption('offset', 'of', InputOption::VALUE_REQUIRED, 'Number of rows in the csv to skip', $this->config->getOffset())
            ->addOption('limit', 'li', InputOption::VALUE_REQUIRED, 'Number of rows in the csv to import', $this->config->getLimit())
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Debug mode');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \JsonMapper_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config->loadOptions($input);

        $csv = Reader::createFromPath($input->getArgument('filename'));

        $input_bom = $csv->getInputBOM();
        if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
            $output->writeln('Converting CSV from UTF-16 to UTF-8\n');
            $csv->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
        }

        $res = $csv
            ->setDelimiter($this->config->getCsvDelimiter())
            ->setOffset($this->config->getOffset())
            ->setLimit($this->config->getLimit())
            ->fetchAll();

        foreach ($res as $line) {
            if ($this->config->getDebug()) {
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

                if ($this->config->getDebug()) {
                    echo "DATE: $date_value TIME: $time_value ISSUE: $issueKey COMMENT: $comment SPENT: $hours\n";
                }

                // Make sure timezone is correct, it can have an impact on the day the timelog is saved into.
                $date = \DateTime::createFromFormat(
                    $this->config->getCsvDateFormat(),
                    $date_value . ' ' . $time_value,
                    new \DateTimeZone($this->config->getCsvDateTimezone())
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
                        if ($this->config->getDebug()) {
                            var_dump($ret);
                        }
                    }
                    else {
                        if ($this->config->getDebug()) {
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