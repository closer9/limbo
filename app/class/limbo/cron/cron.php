<?php
namespace limbo\cron;

use \limbo\error;
use \limbo\util\lock;
use \limbo\web\web;
use \limbo\log;

/**
 * This class manages the processing of cronjobs. You can add new jobs via the add method.
 * The run() method is called to check for jobs that are due to run, and execute() is used
 * to actually execute the script or closure.
 * 
 * @package limbo\cron
 */
class cron
	{
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
	public function __construct ($options = array ())
		{
		if (! empty ($options))
			{
			foreach ($options as $variable => $value)
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
		if (! $this->enabled) return;
		
		foreach ($this->jobs as $name => $settings)
			{
			if (! $settings['enabled'])
				continue;

			$schedule = expr\CronExpression::factory ($settings['schedule']);

			if ($schedule->isDue ())
				{
				if (lock::get ('scheduler.' . $name))
					{
					log::error ('Unable to run scheduled job "' . $name . '". Lock file exists.');

					continue;
					}
				
				log::debug ('Spawning scheduled job "' . $name . '"');
				
				exec ("php " . config('path.app') . "/crons/scheduler.php --process=$name &");
				}
			}
		}
	
	/**
	 * Takes in the name of the job to process and makes sure it's valid. This also 
	 * checks to make sure that job is not still processing by checking for it's lock file.
	 * 
	 * @param string $job	The name of the job to process
	 *
	 * @throws \limbo\error
	 */
	public function process ($job)
		{
		if (! isset ($this->jobs[$job]))
			{
			throw new error ('Unknown scheduled job "' . $job . '"');
			}
		
		if (lock::get ('scheduler.' . $job))
			{
			throw new error ('Unable to run scheduled job "' . $job . '". Lock file exists.');
			}
		
		log::info ('Running scheduled job "' . $job . '"');

		$this->execute ($job);
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
		lock::set ('scheduler.' . $job, 600);
		
		// We want the output to go to a file
		if ($this->jobs[$job]['output'] !== null)
			{
			// Are they specifying a file path?
			if (strpos ($this->jobs[$job]['output'], '/') === false)
				{
				$output = config ('path_log') . $this->jobs[$job]['output'];
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
					log::warning ('Scheduled job "' . $job . '" generated an error: ' . $e);
					}
				
				// Save off the output to where they specified
				file_put_contents ($output, web::clear_buffer ());
				}
			}
		
		// Are we going to execute a script?
		if (! empty ($this->jobs[$job]['script']))
			{
			$script 	= config ('path.app') . '/crons/' . $this->jobs[$job]['script'];
			$options	= $this->jobs[$job]['options'];
			
			if (! is_readable ($script))
				{
				throw new error ('Unable to run the schedule task. Can not find the script "' . $this->jobs[$job]['script'] . '"');
				}

			try {
				system ("php $script $options 1> $output 2>&1");
				}
			catch (\Exception $e)
				{
				log::warning ('Scheduled job "' . $job . '" generated an error: ' . $e);
				}
			}
		
		if (! empty ($this->jobs[$job]['output']))
			{
			// Do we have output to send via e-mail?
			$message = file_get_contents ($output);
			
			if (! empty ($message))
				{
				$this->email ($job, $message);
				}
			}
		
		lock::delete ('scheduler.' . $job);
		}
	
	/**
	 * Send out an e-mail with the jobs output
	 * 
	 * @param string $job		The name of the job to process
	 * @param string $output	The output to e-mail
	 */
	public function email ($job, $output)
		{
		global $SMTP;
		
		if ($this->jobs[$job]['email'])
			{
			log::debug ('Sending e-mail of scheduled job results');

			if (! is_object ($SMTP))
				{
				$SMTP = new \limbo\util\smtp ();
				}

			$SMTP->mail (
				config ('admin.notify'),
				'Scheduler <' . config ('admin.email') . '>',
				'Scheduler output for job "' . $job . '"',
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
	 *
	 * - You must specify either a command or a script (or both)
	 * 
	 * @param string $name
	 * @param array  $settings
	 * 
	 * @throws error
	 */
	public function add ($name, $settings)
		{
		$name = preg_replace ('/\s/', '', $name);
		
		foreach (array ("schedule") as $field)
			{
			if (empty ($settings[$field]))
				{
				throw new error ('Missing "' . $field . '" for scheduled job "' . $name . '"');
				}
			}
		
		if (empty ($settings['command']) && empty ($settings['script']))
			{
			throw new error ('You must specify a script or a command for scheduled job "' . $name . '"');
			}
		
		if (isset ($this->jobs[$name]))
			{
			throw new error ('There is already a scheduled job named "' . $name . '"');
			}
		
		// Setup the defaults
		$this->jobs[$name] = array (
			'command'	=> null,
			'script'	=> null,
			'options'	=> '',
			'schedule'	=> null,
			'output'	=> null,
			'enabled'	=> true,
			'email'		=> false,
			);
		
		// And now overwrite the defaults
		foreach ($settings as $field => $value)
			{
			$this->jobs[$name][$field] = $value;
			}
		}
	}