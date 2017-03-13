# CSV to Jira

This script allows to import a CSV file containing worklogs into Jira.

Install:

```
git clone https://github.com/peterlozano/jira-worklog-import.git
cd jira-worklog-import
composer install
```

Usage:

* Make sure the csv file contains the following fields:
  * Date
  * Jira issue key
  * Description of the worklog
  * Time spent
  
* Adjust column numbers manually in script.

* Run
```
php 