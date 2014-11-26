<?php
namespace limbo\util;

/**
 * Collection of file related methods/functions
 * 
 * Class file
 * @package limbo\util
 */
class file
	{
	/**
	 * Returns the file size in easily readable format using the largest container
	 * 
	 * @param int $input	The size in bytes
	 *
	 * @return bool|string	False if unknown, otherwise it returns the readable size
	 */
	static function size ($input)
		{
		foreach (array ('bytes', 'KB', 'MB', 'GB', 'TB') as $x)
			{
			if ($input < 1024.0) return sprintf ("%3.1f %s", $input, $x);

			$input /= 1024.0;
			}
		
		return false;
		}
	
	/**
	 * Gets the size and mime type of the supplied file
	 * 
	 * @param string $file The full path + name of the file
	 *
	 * @return bool if the file can't be found
	 */
	public static function info ($file)
		{
		if (! is_file ($file))
			{
			\limbo\log::debug ('Could not find file ' . $file);
			
			return false;
			}
		
		$info = new \finfo (FILEINFO_MIME_TYPE);
		
		$output['mime'] = $info->file ($file);
		$output['size'] = filesize ($file);
			
		return $output;
		}
	
	/**
	 * Traverses a directory returning all the files in that directory (recursively or not)
	 * 
	 * @param string $directory		The directory path to look at
	 * @param bool   $full_path		Return the full path or just the file names
	 * @param bool   $recursive		Recursively scan all sub directories
	 * @param string $name			Restrict output to a specific name
	 * @param bool   $_follow		Used when recursively scanning, used to init the static vars
	 *
	 * @return array|bool False if the initial directory is unreadable, returns the file list otherwise
	 */
	static function filelist ($directory, $full_path = false, $recursive = true, $name = '*', $_follow = false)
		{
		static $_source		= '';
		static $_filelist	= array ();

		if ($fp = @opendir ($directory))
			{
			if ($_follow === false)
				{
				$_filelist	= array ();
				$_source	= strlen ($directory);
				$directory	= rtrim (realpath ($directory), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
				}

			while (($file = readdir ($fp)) !== false)
				{
				if ($file[0] !== '.')
					{
					if ($recursive && is_dir ($directory . $file))
						{
						file::filelist ($directory . $file . DIRECTORY_SEPARATOR, $full_path, $name, $recursive, true);
						}
						else
						{
						if ($name != '*' && $file != $name)
							continue;
						
						if ($full_path === true)
							$_filelist[] = $directory . $file;
							else
							$_filelist[] = substr ($directory . $file, $_source);
						}
					}
				}

			closedir ($fp);
			
			return $_filelist;
			}

		return false;
		}
	
	/**
	 * Includes a file with the added benefit of extracting all our globals to it. Very useful
	 * if we want our global variables to be present in all included files.
	 * 
	 * @param $file
	 */
	public static function import ($file)
		{
		extract (\limbo::$globals);
		
		include $file;
		}
	}