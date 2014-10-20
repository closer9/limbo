<?php
namespace limbo;

/**
 * This class is whats used to log message to the application's log file.
 * 
 * Class log
 * @package limbo
 */
class log
	{
	/**
	 * @var resource The main resource for the file socket
	 */
	private static $logger;
	
	/**
	 * Initiate the socket and write data to the log file. We determine the type of 
	 * log (and the file name) based on the Limbo configuration file. We then make sure
	 * that the loging directory exists and is writable. Finally we try to build out 
	 * the entry and write it to the log file.
	 * 
	 * If the message type is below the level we're logging at, log the entry.
	 * 
	 * @param string $input		The message to log
	 * @param string $type		The type of message this is (info, error, debug)
	 * @param int    $level		The level of the message
	 *
	 * @throws error
	 */
	private static function write ($input, $type, $level)
		{
		if (config ('log.type') == 'none') return;
		
		switch (config ('log.type'))
			{
			case 'daily':
				$file = config ('path.storage') . 'logs/' . date ('Y-m-d') . '.log';
				break;
			
			case 'weekly':
				$file = config ('path.storage') . 'logs/' . date ('W') . '.log';
				break;

			case 'monthly':
				$file = config ('path.storage') . 'logs/' . date ('Y-m') . '.log';
				break;
			
			default: return;
			}
		
		// Make sure we're logging that this level
		if ($level <= config ('log.level'))
			{
			// Build the entry
			$output = date ('[Y-m-d H:i:s] - ') . $type . ' - ' . $input . PHP_EOL;
			
			try {
				if (! is_resource (self::$logger))
					{
					if (is_writable (config ('path.storage')))
						{
						// Try to create the logs directory if it's not available
						if (! is_dir (config ('path.storage') . 'logs/'))
							{
							mkdir (config ('path.storage') . 'logs/');
							chmod (config ('path.storage') . 'logs/', 0777);
							}
						
						// Try to create the file
						if (! is_file ($file))
							{
							touch ($file);
							chmod ($file, 0777);
							}
						}
					
					// Open the logfile for appending
					if (! (self::$logger = @fopen ($file, 'a')))
						{
						throw new error ("Unable to open the log file for writing: {$file}", array ('log' => false));
						}
					}
				
				if (is_resource (self::$logger))
					{
					if (! fwrite (self::$logger, $output))
						{
						throw new \exception;
						}
					}
				}
			catch (\exception $e)
				{
				throw new error ("Unable to write to the log file {$file}", array ('log' => false));
				}
			}
		}
	
	/**
	 * This method cleans up the log file directory, getting rid of any log files that
	 * exceed the amount of days specified by log.retain in the config.
	 */
	public static function cleanup ()
		{
		if (config ('log.type') == 'none') return;
		
		$expire_time = time () - (config ('log.retain') * 86400);
		
		foreach (scandir (config ('log.path')) as $logfile)
			{
			$filepath = config ('log.path') . $logfile;
			
			if (is_file ($filepath) && is_writable ($filepath))
				{
				$mtime = filemtime ($filepath);
				
				if ($mtime < $expire_time)
					{
					self::warning ("Log file {$logfile} is past retention and will be deleted");
					
					unlink ($filepath);
					}
				}
			}
		}
	
	/********************************************************************************
	 * Helper methods
	 *******************************************************************************/
	
	static function app ($input, $level = 1)
		{
		self::write ($input, 'app', $level);
		}
	
	static function error ($input, $level = 1)
		{
		self::write ($input, 'error', $level);
		}
	
	static function info ($input, $level = 2)
		{
		self::write ($input, 'info', $level);
		}

	static function warning ($input, $level = 3)
		{
		self::write ($input, 'warning', $level);
		}

	static function debug ($input, $level = 4)
		{
		self::write ($input, 'debug', $level);
		}
	}