<?php
namespace limbo\util;

use \limbo\error;
use \limbo\log;

/**
 * The class handles setting / clearing text based lock files.
 * 
 * Class lock
 * @package limbo\util
 */
class lock
	{
	/**
	 * Sets a new lock file. It will also create the proper directory structure if it
	 * has write access to the storage directory.
	 * 
	 * @param string $lock		The unique name of the lock file
	 * @param int    $expires	How long until the lock file expires (0 = never)
	 * @param string $data		Any additional data to store in the lock
	 *
	 * @throws error
	 */
	static function set ($lock, $expires = 300, $data = '')
		{
		if (is_writable (config ('path.storage')))
			{
			if (! is_dir (config ('path.storage') . '/locks'))
				{
				mkdir (config ('path.storage') . '/locks');
				chmod (config ('path.storage') . '/locks', 0777);
				}
			}
		
		if (! @is_writable (config ('path.storage') . '/locks'))
			{
			throw new error ('Unable to write to the locks directory');
			}
		
		// Check if we already have a lock set with this name
		if (self::get ($lock))
			{
			throw new error ("A lockfile with the name '{$lock}' already exits");
			}
		
		// Create the new lock file
		file_put_contents (
			config ('path.storage') . '/locks/' . $lock . '.lock',
			implode (PHP_EOL, array (
				'@Created:' . date ('Y-m-d H:i:s'),
				'@Expires:'	. (($expires == 0) ? 'Never' : date ('Y-m-d H:i:s', time () + $expires)),
				$data
				))
			);
		
		log::debug ("Created lock file named '{$lock}'");
		}
	
	/**
	 * Check if a lockfile already exists. If there is a lock file but it has expired this 
	 * method will also delete it.
	 * 
	 * @param string $lock The name of the lock file to check for
	 *
	 * @return array|bool Returns the lock data if the lock file exists, false otherwise
	 */
	static function get ($lock)
		{
		$lockfile = config ('path.storage') . '/locks/' . $lock . '.lock';

		if (($lock_data = self::parse ($lockfile)) != false)
			{
			if ($lock_data['expires'] == 'Never' || strtotime ($lock_data['expires']) > time ())
				{
				return $lock_data;
				}

			log::warning ("The lock file named {$lock} timed out. Please investigate.");

			self::delete ($lock);
			}

		return false;
		}
	
	/**
	 * Deletes a lock file.
	 * 
	 * @param string $lock The name of the lock file to delete
	 *
	 * @throws error if the lock file can not be deleted
	 */
	static function delete ($lock)
		{
		$lockfile = config ('path.storage') . '/locks/' . $lock . '.lock';

		if (unlink ($lockfile) === false)
			{
			throw new error ("Unable to delete lock file: {$lockfile}");
			}
		
		log::debug ("Deleted the lock file named '{$lock}'");
		}
	
	/**
	 * Parses the data inside the lock file and returns it as an array.
	 * 
	 * @param string $lockfile The full path to the lock file to parse
	 *
	 * @return array|bool Returns the lockfile data in an array, false otherwise
	 */
	static function parse ($lockfile)
		{
		$return = array (
			'created'	=> null,
			'expires'	=> null,
			'data'		=> '',
			);

		if (is_file ($lockfile))
			{
			foreach (file ($lockfile) as $line)
				{
				if (strpos ($line, '@Created:') === 0)
					{
					$return['created'] = trim (substr ($line, 9));
					}
				elseif (strpos ($line, '@Expires:') === 0)
					{
					$return['expires'] = trim (substr ($line, 9));
					}
					else
					{
					$return['data'] .= $line;
					}
				}
			}

		return ($return['created'] !== null) ? $return : false;
		}
	}