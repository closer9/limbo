<?php
/******************************************************************************
Add this script to your crontab with the following line:
* * * * * php /path/to/scheduler.php 1>> /dev/null 2>&1
*******************************************************************************
Minute (0-59)
|  Hour (0-23)
|  |  Day of the month (1-31)
|  |  |  Month of the year (1-12)
|  |  |  |  Day of the week (0-6, Sun-Sat)
|  |  |  |  |
*  *  *  *  *
*******************************************************************************
You can also use the following expressions for schedules

@yearly 	= '0 0 1 1 *'
@annually 	= '0 0 1 1 *'
@monthly 	= '0 0 1 * *'
@weekly 	= '0 0 * * 0'
@daily 		= '0 0 * * *'
@hourly 	= '0 * * * *'
*******************************************************************************
Example usage of the $cron->add() method

$cron->add ('job-name', $option_array);
*******************************************************************************
Options available for scheduled jobs

Command		Default		Required	Description
-----------------------------------------------------------
command		null		Yes *		A closure function to execute
script		null		Yes *		A .php script in the app/crons directory
options		''			No			CLI options for the script
schedule	null		Yes			The jobs cron expression schedule (* * * * *)
output		null		No			A filename to save the output to
enabled		true		No			Specifies if the job will run or not
email		false		No			E-mail the output (must save to a file)

* - You must specify either a command or a script (or both)
******************************************************************************/

require (__DIR__ . '/../class/limbo.php');

use \limbo\cli\args;
use \limbo\cron\cron;

$l 		= new limbo ();
$flags 	= args::parse_args ($argv);

// Show the help
if (args::check_args ($flags, 'h,help'))
	{
	echo 'Usage: scheduler.php [OPTIONS]' . PHP_EOL;
	echo '  -h, --help          Display this help and exit' . PHP_EOL;
	echo '  --process=[name]    Process a scheduled job immediately' . PHP_EOL;
	
	exit;
	}

// Setup the cron processing class
$cron = new cron ();

// Default cronjob to clean up old log files
$cron->add ('logfile-cleanup', array (
	'command'	=> function () { \limbo\log::cleanup (); },
	'schedule'	=> '@daily',
	'enabled'	=> true,
	));

if (args::check_args ($flags, 'process'))
	{
	// We're told to process a specific job
	$cron->process ($flags['process']);
	}
	else
	{
	// Check for ready jobs
	$cron->run ();
	}
