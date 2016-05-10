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
		if (! config ('log.enable')) return;
		
		// Make sure we're logging at the allowed level
		if ($level <= config ('log.level'))
			{
			$file	= config ('log.path') . date (config ('log.format')) . '.log';
			$output	= date ('[Y-m-d H:i:s] - ') . $type . ' - ' . $input . PHP_EOL;
			
			try {
				if (! is_resource (self::$logger))
					{
					if (is_writable (config ('path.storage')))
						{
						// Try to create the log directory if it's not available
						if (! is_dir (config ('log.path')))
							{
							mkdir (config ('log.path'));
							chmod (config ('log.path'), 0777);
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
		if (! config ('log.enable')) return;
		
		// Get the date of the oldest logfile to keep
		$expire_time = util\time::modify_date (time (), '-', config ('log.retain'));
		
		foreach (scandir (config ('log.path')) as $logfile)
			{
			$filepath = config ('log.path') . $logfile;
			
			if (is_file ($filepath) && is_writable ($filepath))
				{
				if (config ('log.retain') > 0 && filemtime ($filepath) < $expire_time)
					{
					self::info ("Log file {$logfile} is past retention and will be deleted");
					
					unlink ($filepath);
					}
				
				if (config ('log.compress'))
					{
					$previous = date (config ('log.format'), util\time::modify_date (time (), '-', 1));
					
					if ($logfile == "{$previous}.log")
						{
						self::info ("Compressing the previous days log file: {$logfile}");
						
						util\file::compress ($filepath, 9, true);
						}
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
	
	static function warning ($input, $level = 2)
		{
		self::write ($input, 'warning', $level);
		}
	
	static function info ($input, $level = 3)
		{
		self::write ($input, 'info', $level);
		}

	static function debug ($input, $level = 4)
		{
		self::write ($input, 'debug', $level);
		}
	}