# CSV to Jira

This script allows to import a CSV file containing worklogs into Jira.

###Install:

```
git clone https://github.com/peterlozano/jira-worklog-import.git
cd jira-worklog-import
composer install
```

Create a .env file at least with the following info:

```
JIRA_HOST="https://<SUBDOMAIN>.atlassian.net"
JIRA_USER=""
JIRA_PASS=""
```

### Usage:

* Make sure the csv file contains the following fields:
  * Date
  * Jira issue key
  * Description of the worklog
  * Time spent (in `HH:MM:SS` format)
  
* Adjust column numbers manually in script.

```
php jira-worklog-import.php app:import <CSVFILE>
```

* Look at output to see if columns were parsed correctly.
* Do do a real import use --real-import

```
php jira-worklog-import.php app:import --real-import <CSVFILE>
```

### Other options

See usage with:

```
php jira-worklog-import.php app:import --help
```

* --debug
  * Enables additional output.
* --csv-date-format=CSV-DATE-FORMAT
  * Allows to specify the date format in the csv file.
* --csv-date-timezone=CSV-DATE-TIMEZONE
  * Allows to specify the timezone used in the csv file dates.
* --csv-delimiter=CSV-DELIMITER
  * Allows to speficy the character used as delimiter in the csv file.
* --offset=OFFSET
  * Allows to skip OFFSET number of lines from the top of the csv file.
* --limit=LIMIT
  * Only import LIMIT number of lines.
  
Some options can be included in the .env file to avoid writing them on the command each time.

```
CSV_DATE_FORMAT="Y-m-d H:i:s"
CSV_DATE_TIMEZONE="Europe/Madrid"
CSV_DELIMITER=","
OFFSET=1
LIMIT=1000
DEBUG=false
```  