<?php
namespace limbo\cron;

use \limbo\util\lock;
use \limbo\web\web;
use \limbo\log;
use \limbo\mysql;
use \limbo\util\smtp;

/**
 * This class manages the processing of cronjobs. You can add new jobs via the add method.
 * The run() method is called to check for jobs that are due to run, and execute() is used
 * to actually execute the script or closure.
 *
 * @package limbo\cron
 */
class cron {
	/**
	 * A collection of jobs to process
	 *
	 * @var
	 */
	private $jobs;
	
	/**
	 * Master enable switch
	 *
	 * @var bool
	 */
	private $enabled = true;
	
	/**
	 * Construct the class and set any specified options
	 *
	 * @param array $params
	 */
	public function __construct ($params = array ())
		{
		if (! empty ($params))
			{
			foreach ($params as $variable => $value)
				{
				$this->$variable = $value;
				}
			}
		}
	
	/**
	 * Processes the list of jobs and determines if any of them are scheduled to run. If
	 * they are scheduled it calls the scheduler script telling it to process the specified
	 * job. The reason it's done this way is to make sure the jobs are spun off into their
	 * own process.
	 */
	public function run ()
		{
		if (! $this->enabled || config ('cron.enabled') === false) return;
		
		if (config ('cron.table'))
			{
			$SQL = new mysql (config ('database.options'));
			$SQL->connect ();
			
			$build_file = config ('path.app') . 'sql/cron.php';
			
			// Check if we're allowed to build tables and if there is a SQL file
			if (config ('limbo.build_tables') && is_readable ($build_file))
				{
				log::debug ('CONFIG - Attempting to create the cron database table');
				
				require ($build_file);
				
				if (isset ($build))
					{
					$SQL->create_table (config ('cron.table'), $build);
					}
				}
			
			while ($job = $SQL->loop ("SELECT * FROM `" . config ('cron.table') . "` WHERE `enabled` = 1"))
				{
				// Remove any previously set jobs with this name.
				if (isset ($this->jobs[$job['process']]))
					{
					unset ($this->jobs[$job['process']]);
					}
				
				$this->add ($job['process'], (array) $job);
				}
			}
		
		foreach ($this->jobs as $name => $settings)
			{
			if (! $settings['enabled'])
				continue;
			
			// Skip if this is a non-production only job and we're production
			if ($settings['runmode'] === 1 && config ('limbo.production'))
				{
				log::debug ("Skipping cronjob '{$name}' because it's a non-production only job");
				
				continue;
				}
			
			// Skip if this is a production only job and we're not production
			if ($settings['runmode'] === 2 && ! config ('limbo.production'))
				{
				log::debug ("Skipping cronjob '{$name}' because its a production only job");
				
				continue;
				}
			
			$schedule = expr\CronExpression::factory ($settings['schedule']);
			
			log::debug ("CRON - Checking if job '{$name}' is due");
			
			if ($schedule->isDue ())
				{
				if (lock::get ("scheduler.{$name}"))
					{
					log::error ("CRON - Unable to run job '{$name}' because a lock file exists");
					
					continue;
					}
				
				log::debug ("CRON - Spawning scheduled job '{$name}'");
				
				exec ("nohup php " . config('path.app') . "/crons/scheduler.php --process={$name} > /dev/null 2>&1 &");
				}
			}
		}
	
	/**
	 * Takes in the name of the job to process and makes sure it's valid. This also
	 * checks to make sure that job is not still processing by checking for it's lock file.
	 *
	 * @param string $job	The name of the job to process
	 */
	public function process ($job)
		{
		log::info ("CRON - Running scheduled job '{$job}'");
		
		if (lock::get ("scheduler.{$job}"))
			{
			log::warning ("CRON - Unable to run job '{$job}' because a lock file exists");
			}
		
		if (config ('cron.table'))
			{
			$SQL = new mysql (config ('database.options'));
			$SQL->connect ();
			
			$cron = $SQL->prefetch ("SELECT * FROM `?` WHERE `process` = ?", array (
				config ('cron.table'),
				$job
				));
			
			if (isset ($cron['process']))
				{
				$SQL->update ($cron['process'], array (
					'lastrun' => date ('Y-m-d H:i:s')
				), config ('cron.table'), 'process');
				
				// Remove any previously set jobs with this name.
				if (isset ($this->jobs[$job]))
					{
					unset ($this->jobs[$job]);
					}
				
				$this->add ($cron['process'], (array) $cron);
				}
			
			$SQL->disconnect ();
			}
		
		if (isset ($this->jobs[$job]))
			{
			$this->execute ($job);
			
			return;
			}
		
		log::error ("CRON - Unknown scheduled job '{$job}'");
		}
	
	/**
	 * This is the method that actually runs the jobs. It takes in the job name and sets up
	 * the lock file. It then sets up any output paths and figures out if we want to call
	 * a script or execute a closure.
	 *
	 * @param string $job The name of the job to process
	 *
	 * @throws \limbo\error
	 */
	public function execute ($job)
		{
		lock::set ("scheduler.{$job}", $this->jobs[$job]['timeout']);
		
		// We want the output to go to a file
		if ($this->jobs[$job]['output'] !== null)
			{
			// Are they specifying a file path?
			if (strpos ($this->jobs[$job]['output'], '/') === false)
				{
				if ($this->jobs[$job]['output'] === true || $this->jobs[$job]['output'] === '1')
					$output = config ('path.storage') . "logs/{$job}.txt";
					else
					$output = config ('path.storage') . "logs/{$this->jobs[$job]['output']}";
				}
				else
				{
				$output = $this->jobs[$job]['output'];
				}
			}
			else
			{
			$output = '/dev/null';
			}
		
		// Are we going to execute a closure?
		if (! empty ($this->jobs[$job]['command']))
			{
			if (gettype ($this->jobs[$job]['command']) === 'object')
				{
				ob_start();
				
				try {
					$this->jobs[$job]['command']();
					}
				catch (\Exception $e)
					{
					log::error ("CRON - Scheduled job '{$job}' generated an error: {$e}");
					}
				
				// Save off the output to where they specified
				file_put_contents ($output, web::clear_buffer ());
				}
			}
		
		// Are we going to execute a script?
		if (! empty ($this->jobs[$job]['script']))
			{
			$script 	= config ('path.app') . "crons/{$this->jobs[$job]['script']}";
			$options	= $this->jobs[$job]['options'];
			
			if (! is_readable ($script))
				{
				log::error ("CRON - Unable to run job {$job}. Can not find the script: {$this->jobs[$job]['script']}");
				}
			
			try {
				log::debug ("CRON - Running | php {$script} {$options} 1> {$output} 2>&1");
				
				system ("php $script $options 1> $output 2>&1");
				}
			catch (\Exception $e)
				{
				log::error ("CRON - Scheduled job '{$job}' generated an error: {$e}");
				}
			}
		
		if ($output != '/dev/null' && is_file ($output))
			{
			// Do we have output to send via e-mail?
			$message = @file_get_contents ($output);
			
			if (! empty ($message))
				{
				$this->email ($job, $message);
				}
			}
		
		lock::delete ("scheduler.{$job}");
		}
	
	/**
	 * Send out an e-mail with the jobs output
	 *
	 * @param string $job		The name of the job to process
	 * @param string $output	The output to e-mail
	 */
	public function email ($job, $output)
		{
		if ($this->jobs[$job]['email'])
			{
			log::debug ('CRON - Sending e-mail of scheduled job results');
			
			$smtp = new smtp ();
			
			$smtp->mail (
				config ('admin.notify'),
				'Scheduler <' . config ('admin.email') . '>',
				"Scheduler output for job: {$job}",
				$output
				);
			}
		}
	
	/**
	 * Adds a cronjob into the job queue.
	 *
	 * Example:
	 * $cron->add ('job-name', $option_array);
	 *
	 * ******************************************************************************
	 * Options available for scheduled jobs
	 *
	 * Command     Default     Required    Description
	 * -----------------------------------------------------------
	 * command     null        Yes *        A closure function to execute
	 * script      null        Yes *        A script in the app/crons directory
	 * options     ''          No           CLI options for the script
	 * schedule    null        Yes          The jobs cron expression schedule (* * * * *)
	 * output      null        No           A filename to save the output to
	 * enabled     true        No           Specifies if the job will run or not
	 * email       false       No           E-mail the output (must save to a file)
	 * production  'both'      No           Runs on production or not or both
	 * timeout     600         No           Time until the job is considered timed-out
	 *
	 * - You must specify either a command or a script (or both)
	 *
	 * @param string $name
	 * @param array  $settings
	 */
	public function add ($name, $settings)
		{
		$name = preg_replace ('/\s/', '', $name);
		
		log::debug ("CRON - Adding new job: {$name}");
		
		foreach (array ("schedule") as $field)
			{
			if (empty ($settings[$field]))
				{
				log::error ("Missing '{$field}' for scheduled job '{$name}'");
				}
			}
		
		if (empty ($settings['command']) && empty ($settings['script']))
			{
			log::error ("You must specify a script or a command for scheduled job '{$name}'");
			}
		
		if (isset ($this->jobs[$name]))
			{
			log::error ("CRON - There is already a scheduled job named '{$name}'");
			}
		
		// Setup the defaults
		$this->jobs[$name] = array (
			'command'	 => null,
			'script'	 => null,
			'options'	 => '',
			'schedule'	 => null,
			'output'	 => null,
			'enabled'	 => true,
			'email'		 => false,
			'runmode'    => 0,
			'timeout'    => 600,
		);
		
		// And now overwrite the defaults
		foreach ($settings as $field => $value)
			{
			$this->jobs[$name][$field] = $value;
			}
		}
	}
