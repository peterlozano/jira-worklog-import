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

    private $csv_date_format;
    private $csv_date_timezone;
    private $csv_delimiter;
    private $offset;
    private $limit;
    private $debug;

    protected function configure()
    {
        $this->loadEnvConfig();

        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:import')
            // the short description shown while running "php bin/console list"
            ->setDescription('Import data.')
            ->addArgument('filename', InputArgument::REQUIRED, 'The filename to import from.')
            ->addOption('real-import', 'ri', InputOption::VALUE_NONE, 'Disable the test mode and do a real import.')
            ->addOption('csv-date-format', 'df', InputOption::VALUE_REQUIRED, 'Specify a custom date format in the csv.', $this->csv_date_format)
            ->addOption('csv-date-timezone', 'tz', InputOption::VALUE_REQUIRED, 'Specify a custom timezone in the csv.', $this->csv_date_timezone)
            ->addOption('csv-delimiter', 'dl', InputOption::VALUE_REQUIRED, 'Specify the csv delimiter.', $this->csv_delimiter)
            ->addOption('offset', 'of', InputOption::VALUE_REQUIRED, 'Number of rows in the csv to skip', $this->offset)
            ->addOption('limit', 'li', InputOption::VALUE_REQUIRED, 'Number of rows in the csv to import', $this->limit)
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Debug mode');

    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadOptions($input);

        $csv = Reader::createFromPath($input->getArgument('filename'));

        $input_bom = $csv->getInputBOM();
        if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
            $output->writeln('Converting CSV from UTF-16 to UTF-8\n');
            $csv->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
        }


        $res = $csv
            ->setDelimiter($this->csv_delimiter)
            ->setOffset($this->offset)
            ->setLimit($this->limit)
            ->fetchAll();

        foreach ($res as $line) {
            if ($this->debug) {
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

                if ($this->debug) {
                    echo "DATE: $date_value TIME: $time_value ISSUE: $issueKey COMMENT: $comment SPENT: $hours\n";
                }

                // Make sure timezone is correct, it can have an impact on the day the timelog is saved into.
                $date = \DateTime::createFromFormat(
                    $this->csv_date_format,
                    $date_value . ' ' . $time_value,
                    new \DateTimeZone($this->csv_date_timezone)
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
                        if ($this->debug) {
                            var_dump($ret);
                        }
                    }
                    else {
                        if ($this->debug) {
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

    private function loadEnvConfig() {
        $this->loadDotEnv();

        if ($csv_date_format = $this->env('CSV_DATE_FORMAT')) {
            $this->csv_date_format = $csv_date_format;
        }
        else {
            $this->csv_date_format = DEFAULT_DATE_FORMAT;
        }

        if ($csv_date_timezone = $this->env('CSV_DATE_TIMEZONE')) {
            $this->csv_date_timezone = $csv_date_timezone;
        }
        else {
            $this->csv_date_timezone = DEFAULT_DATE_TIMEZONE;
        }

        if ($csv_delimiter = $this->env('CSV_DELIMITER')) {
            $this->csv_delimiter = $csv_delimiter;
        }
        else {
            $this->csv_delimiter = ',';
        }

        if ($offset = $this->env('OFFSET')) {
            $this->offset = $offset;
        }
        else {
            $this->offset = 1;
        }

        if ($limit = $this->env('LIMIT')) {
            $this->limit = $limit;
        }
        else {
            $this->limit = 1000;
        }

        if ($debug = $this->env('DEBUG')) {
            $this->debug = $debug;
        }
        else {
            $this->debug = false;
        }
    }


    private function loadDotEnv() {
        // support for dotenv 1.x and 2.x. see also https://github.com/lesstif/php-jira-rest-client/issues/102
        if (class_exists('\Dotenv\Dotenv')) {
            $dotenv = new \Dotenv\Dotenv('.');

            $dotenv->load();
        } elseif (class_exists('\Dotenv')) {
            \Dotenv::load('.');
        } else {
            throw new JiraException('can not load PHP dotenv class.!');
        }
    }

    /**
     * Gets the value of an environment variable. Supports boolean, empty and null.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    private function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'empty':
            case '(empty)':
                return '';

            case 'null':
            case '(null)':
                return;
        }

        if ($this->startsWith($value, '"') && $this->endsWith($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function loadOptions(InputInterface $input)
    {
        if ($input->getOption('csv-date-format')) {
            $this->csv_date_format = $input->getOption('csv-date-format');
        }
        if ($input->getOption('csv-date-timezone')) {
            $this->csv_date_timezone = $input->getOption('csv-date-timezone');
        }
        if ($input->getOption('limit')) {
            $this->limit = $input->getOption('limit');
        }
        if ($input->getOption('offset')) {
            $this->offset = $input->getOption('offset');
        }
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && strpos($haystack, $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public function endsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle === substr($haystack, -strlen($needle))) {
                return true;
            }
        }

        return false;
    }
}